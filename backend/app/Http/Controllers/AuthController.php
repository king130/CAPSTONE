<?php

namespace App\Http\Controllers;

use App\Models\AccountSetupToken;
use App\Models\Company;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Permission;
use App\Models\Role;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Services\OrganizationAccountProvisioner;
use App\Services\SubscriptionPlanService;
use App\Services\TenantRoleService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Throwable;

class AuthController extends Controller
{
    public function __construct(
        private readonly SubscriptionPlanService $subscriptionPlans,
        private readonly TenantRoleService $tenantRoleService,
        private readonly OrganizationAccountProvisioner $organizationAccountProvisioner,
    )
    {
    }

    public function register(Request $request)
    {
        try {
            if ($passwordError = $this->passwordPolicyError($request->input('password'))) {
                return response()->json([
                    'message' => $passwordError,
                    'errors' => ['password' => [$passwordError]],
                ], Response::HTTP_BAD_REQUEST);
            }

            $data = $request->validate([
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', 'string', 'min:8', 'regex:/[A-Za-z]/', 'regex:/[0-9]/'],
                'fullName' => ['required', 'string', 'max:255'],
                'role' => ['nullable', Rule::in(['student', 'school', 'company', 'guest', null])],
                'profile' => ['nullable', 'array'],
                'subscriptionPlan' => ['nullable', 'string'],
                'billingCycle' => ['nullable', 'string'],
                'schoolSubscriptionCode' => ['nullable', 'string'],
            ], $this->passwordValidationMessages());

            $role = $data['role'] ?? 'guest';
            $profile = $data['profile'] ?? [];

            // Student accounts are school-provisioned only (roster invite + account setup).
            if ($role === 'student') {
                return response()->json([
                    'message' => 'Student accounts are created by your school. Use the school-issued setup link or login credentials instead of public registration.',
                    'errors' => [
                        'role' => ['Student accounts must be provisioned by your school.'],
                    ],
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            if ($role === 'school' || $role === 'company') {
                $created = $this->organizationAccountProvisioner->create([
                    'fullName' => $data['fullName'],
                    'email' => $data['email'],
                    'password' => $data['password'],
                    'role' => $role,
                    'profile' => $profile,
                    'subscriptionPlan' => $data['subscriptionPlan'] ?? 'free',
                    'billingCycle' => $data['billingCycle'] ?? 'monthly',
                ]);

                $user = $created['user'];
                $this->loadUserRelations($user);
                $token = $user->createToken('spa')->plainTextToken;

                return response()->json([
                    'token' => $token,
                    'user' => $this->formatUserProfile($user),
                ], Response::HTTP_CREATED);
            }

            $user = DB::transaction(function () use ($data, $role, $profile) {
                return User::query()->create([
                    'name' => trim($data['fullName']),
                    'email' => strtolower(trim($data['email'])),
                    'password' => $data['password'],
                    'role' => $role === null ? 'guest' : $role,
                    'profile' => $profile,
                    'is_active' => true,
                    'is_temporary' => false,
                    'must_change_password' => false,
                    'profile_setup_complete' => false,
                ]);
            });

            $this->loadUserRelations($user);
            $token = $user->createToken('spa')->plainTextToken;

            return response()->json([
                'token' => $token,
                'user' => $this->formatUserProfile($user),
            ], Response::HTTP_CREATED);
        } catch (ValidationException $e) {
            throw $e;
        } catch (QueryException $e) {
            $sqlState = (string) ($e->errorInfo[0] ?? '');
            $driverCode = (string) ($e->errorInfo[1] ?? '');
            $message = (string) $e->getMessage();

            if (
                $sqlState === '23000' &&
                ($driverCode === '1062' || str_contains($message, 'Integrity constraint violation')) &&
                (str_contains($message, 'users_email_unique') || str_contains($message, 'Duplicate entry'))
            ) {
                return response()->json([
                    'message' => 'Email address is already registered.',
                    'errors' => ['email' => ['Email address is already registered.']],
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            throw $e;
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Registration is temporarily unavailable. Please try again later.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function login(Request $request)
    {
        try {
            $credentials = $request->validate([
                'email' => ['required', 'email'],
                'password' => ['required', 'string'],
            ]);

            $email = strtolower(trim($credentials['email']));

            $user = User::query()->where('email', $email)->first();
            if (! $user || ! Hash::check($credentials['password'], $user->password)) {
                return response()->json(['message' => 'Invalid credentials.'], Response::HTTP_UNAUTHORIZED);
            }

            if (! $user->is_active) {
                return response()->json(['message' => 'Account Disabled'], Response::HTTP_FORBIDDEN);
            }

            $this->loadUserRelations($user);
            $token = $user->createToken('spa')->plainTextToken;

            return response()->json([
                'token' => $token,
                'user' => $this->formatUserProfile($user),
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Login is temporarily unavailable. Please try again later.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function logout(Request $request)
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logged out']);
    }

    public function me(Request $request)
    {
        $user = $request->user();
        $this->loadUserRelations($user);

        return response()->json($this->formatUserProfile($user));
    }

    public function updatePassword(Request $request)
    {
        if ($passwordError = $this->passwordPolicyError($request->input('password'))) {
            return response()->json([
                'message' => $passwordError,
                'errors' => ['password' => [$passwordError]],
            ], Response::HTTP_BAD_REQUEST);
        }

        $data = $request->validate([
            'password' => ['required', 'string', 'min:8', 'regex:/[A-Za-z]/', 'regex:/[0-9]/', 'confirmed'],
        ], $this->passwordValidationMessages());

        $request->user()->update([
            'password' => $data['password'],
            'must_change_password' => false,
            'is_temporary' => false,
        ]);

        return response()->json(['message' => 'Password updated']);
    }

    public function validateAccountSetupToken(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
        ]);

        $setupToken = $this->findValidAccountSetupToken($data['token']);
        if (! $setupToken) {
            return response()->json([
                'message' => 'That account setup link is invalid or has expired.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user = $setupToken->user;

        return response()->json([
            'data' => [
                'email' => $user->email,
                'displayName' => $user->name,
                'expiresAt' => $setupToken->expires_at?->toIso8601String(),
            ],
        ]);
    }

    public function completeAccountSetup(Request $request)
    {
        if ($passwordError = $this->passwordPolicyError($request->input('password'))) {
            return response()->json([
                'message' => $passwordError,
                'errors' => ['password' => [$passwordError]],
            ], Response::HTTP_BAD_REQUEST);
        }

        $data = $request->validate([
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'regex:/[A-Za-z]/', 'regex:/[0-9]/', 'confirmed'],
        ], $this->passwordValidationMessages());

        $setupToken = $this->findValidAccountSetupToken($data['token']);
        if (! $setupToken) {
            return response()->json([
                'message' => 'That account setup link is invalid or has expired.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user = DB::transaction(function () use ($data, $setupToken) {
            $setupToken->refresh();
            $user = $setupToken->user()->firstOrFail();

            $user->password = $data['password'];
            $user->must_change_password = false;
            $user->is_temporary = false;
            $user->profile_setup_complete = true;
            $user->email_verified_at = now();
            $user->save();

            AccountSetupToken::query()
                ->where('user_id', $user->id)
                ->whereNull('used_at')
                ->update(['used_at' => now()]);

            return $user;
        });

        $this->loadUserRelations($user);
        $token = $user->createToken('spa')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $this->formatUserProfile($user),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function formatUserProfile(User $user): array
    {
        $profile = $user->profile ?? [];
        $primaryMembership = $user->primaryOrganizationMembership();
        $activeOrganization = $primaryMembership?->organization;
        $subscription = $activeOrganization?->subscription;
        $planDefinition = $activeOrganization ? $this->subscriptionPlans->getPlanForOrganization($activeOrganization) : null;
        $overages = $activeOrganization?->settings['subscription_overages'] ?? ['requiresAction' => false, 'items' => []];

        if ($user->relationLoaded('student') && $user->student) {
            $s = $user->student;
            $profile['internCode'] = $s->intern_code;
            $profile['studentNumber'] = $s->student_id_number;
            $profile['studentId'] = $s->student_id_number;
            $profile['schoolName'] = $s->school_name;
            $profile['schoolId'] = $s->school_id ? (string) $s->school_id : '';
            $profile['course'] = $s->course;
            $profile['yearLevel'] = $s->year_level;
        }

        $verificationStatus = null;

        if ($user->relationLoaded('company') && $user->company) {
            $c = $user->company;
            $profile['companyName'] = $c->company_name;
            $profile['companyType'] = $c->company_type;
            $profile['industryType'] = $c->industry_type;
            $profile['companyAddress'] = $c->company_address;
            $profile['companyEmail'] = $c->company_email;
            $profile['contactPersonName'] = $c->contact_person_name;
            $profile['contactPersonTitle'] = $c->contact_person_title;
            $profile['contactPersonEmail'] = $c->contact_person_email;
            $profile['companyContactNumber'] = $c->company_contact_number;
            $verificationStatus = $c->verification_status;
        }

        if ($user->relationLoaded('school') && $user->school) {
            $sch = $user->school;
            $profile['institutionName'] = $sch->institution_name;
            $profile['institutionType'] = $sch->institution_type;
            $profile['department'] = $sch->department;
            $profile['position'] = $sch->position;
            $profile['officialSchoolEmail'] = $sch->official_school_email;
            $profile['schoolContactNumber'] = $sch->school_contact_number;
            $profile['schoolAddress'] = $sch->school_address;
            $verificationStatus = $sch->verification_status;
        }

        if ($activeOrganization) {
            $profile['organizationId'] = (string) $activeOrganization->id;
            $profile['organizationType'] = $activeOrganization->type;
            $profile['organizationName'] = $activeOrganization->name;
        }

        $verificationRejectionReason = null;
        if ($activeOrganization && is_array($activeOrganization->settings)) {
            $reason = $activeOrganization->settings['verification_rejection_reason'] ?? null;
            if (is_string($reason) && trim($reason) !== '') {
                $verificationRejectionReason = trim($reason);
            }
        }

        if ($verificationStatus !== null) {
            $profile['verificationStatus'] = (string) $verificationStatus;
        }
        if ($verificationRejectionReason !== null) {
            $profile['verificationRejectionReason'] = $verificationRejectionReason;
        }

        $appRole = $user->effectiveAppRole();

        return [
            'uid' => (string) $user->id,
            'email' => $user->email,
            'displayName' => $user->name,
            'role' => $appRole,
            'legacyRole' => $user->role,
            'isTemporary' => $user->is_temporary,
            'isActive' => $user->is_active,
            'verificationStatus' => $verificationStatus !== null ? (string) $verificationStatus : null,
            'verificationRejectionReason' => $verificationRejectionReason,
            'profileSetupComplete' => $user->profile_setup_complete,
            'mustChangePassword' => $user->must_change_password,
            'profile' => $profile,
            'accessScope' => $user->isPlatformAdmin() ? 'platform' : ($activeOrganization ? 'organization' : 'personal'),
            'primaryMembershipRole' => $primaryMembership?->role?->slug,
            'platformRole' => $user->platformRole ? [
                'id' => (string) $user->platformRole->id,
                'slug' => $user->platformRole->slug,
                'name' => $user->platformRole->name,
                'permissions' => $user->platformPermissionKeys(),
            ] : null,
            'memberships' => $user->activeMemberships()->map(function (OrganizationMembership $membership) {
                return [
                    'id' => (string) $membership->id,
                    'organization' => [
                        'id' => (string) $membership->organization->id,
                        'name' => $membership->organization->name,
                        'type' => $membership->organization->type,
                        'isActive' => $membership->organization->is_active,
                    ],
                    'role' => [
                        'id' => (string) $membership->role->id,
                        'slug' => $membership->role->slug,
                        'name' => $membership->role->name,
                        'scope' => $membership->role->scope,
                    ],
                    'title' => $membership->title,
                    'status' => $membership->status,
                    'permissions' => $membership->effectivePermissions(),
                ];
            })->values()->all(),
            'activeOrganization' => $activeOrganization ? [
                'id' => (string) $activeOrganization->id,
                'name' => $activeOrganization->name,
                'type' => $activeOrganization->type,
                'subscriptionId' => $activeOrganization->subscription_id ? (string) $activeOrganization->subscription_id : null,
            ] : null,
            'subscription' => $subscription ? [
                'plan' => $subscription->plan,
                'billingCycle' => $subscription->billing_cycle,
                'status' => $subscription->status,
                'subscriptionCode' => $user->school?->subscription_code,
                'pendingChange' => $activeOrganization?->settings['pending_plan_change'] ?? null,
                'limits' => $planDefinition ? $this->subscriptionPlans->serializePlan($planDefinition)['limits'] : null,
                'overages' => $overages,
            ] : null,
            'createdAt' => $user->created_at?->toIso8601String(),
            'updatedAt' => $user->updated_at?->toIso8601String(),
        ];
    }

    private function loadUserRelations(User $user): void
    {
        $user->load([
            'student',
            'company',
            'school',
            'platformRole.permissions',
            'organizationMemberships.role.permissions',
            'organizationMemberships.organization.subscription',
        ]);
    }

    private function findValidAccountSetupToken(string $plainToken): ?AccountSetupToken
    {
        $tokenHash = hash('sha256', $plainToken);

        return AccountSetupToken::query()
            ->with('user')
            ->where('token_hash', $tokenHash)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();
    }

    private function attachOrganizationMembership(User $user, Organization $organization, string $roleSlug, string $title = ''): void
    {
        $this->tenantRoleService->ensureDefaultRoles($organization);

        $role = Role::query()
            ->where('tenant_id', $organization->id)
            ->where('slug', $roleSlug)
            ->first();

        if (! $role && $roleSlug === 'intern') {
            $role = Role::query()
                ->where('tenant_id', $organization->id)
                ->where('slug', 'student_member')
                ->first();
        }

        if (! $role) {
            $role = Role::query()->where('slug', $roleSlug)->whereNull('tenant_id')->first();
        }

        if (! $role) {
            return;
        }

        OrganizationMembership::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'user_id' => $user->id,
            ],
            [
                'role_id' => $role->id,
                'status' => 'active',
                'title' => $title ?: $role->name,
            ]
        );

        $user->forceFill([
            'tenant_id' => $organization->id,
            'role_id' => $role->id,
        ])->save();
    }

    /**
     * Match frontend RegisterSimple policy: min 8 chars, at least one letter and one number.
     */
    private function passwordPolicyError(mixed $password): ?string
    {
        $value = is_string($password) ? $password : '';
        $message = 'Password must be at least 8 characters and include a letter and a number';

        if (strlen($value) < 8 || ! preg_match('/[A-Za-z]/', $value) || ! preg_match('/[0-9]/', $value)) {
            return $message;
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function passwordValidationMessages(): array
    {
        $message = 'Password must be at least 8 characters and include a letter and a number';

        return [
            'password.min' => $message,
            'password.regex' => $message,
        ];
    }
}
