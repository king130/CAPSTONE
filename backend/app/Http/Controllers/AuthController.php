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
            $data = $request->validate([
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', 'string', 'min:8'],
                'fullName' => ['required', 'string', 'max:255'],
                'role' => ['nullable', Rule::in(['student', 'school', 'company', 'guest', null])],
                'profile' => ['nullable', 'array'],
                'subscriptionPlan' => ['nullable', 'string'],
                'billingCycle' => ['nullable', 'string'],
                'schoolSubscriptionCode' => ['nullable', 'string'],
            ]);

            $role = $data['role'] ?? 'guest';
            $profile = $data['profile'] ?? [];

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

            $school = null;
            if ($role === 'student' && ! empty($data['schoolSubscriptionCode'])) {
                $school = School::query()
                    ->with('organization')
                    ->where('subscription_code', $data['schoolSubscriptionCode'])
                    ->whereHas('user', fn ($q) => $q->where('is_active', true))
                    ->first();
                if (! $school) {
                    return response()->json(['message' => 'School subscription code is invalid or inactive.'], Response::HTTP_UNPROCESSABLE_ENTITY);
                }
            }

            $user = DB::transaction(function () use ($data, $role, $profile, $school) {
                $user = User::query()->create([
                    'name' => trim($data['fullName']),
                    'email' => strtolower(trim($data['email'])),
                    'password' => $data['password'],
                    'role' => $role === null ? 'guest' : $role,
                    'profile' => $profile,
                    'is_active' => true,
                    'is_temporary' => false,
                    'must_change_password' => false,
                    'profile_setup_complete' => in_array($role, ['student', 'school', 'company'], true),
                ]);

                if ($role === 'student') {
                    $schoolId = null;
                    $organizationId = null;
                    if (! empty($profile['schoolId'])) {
                        $schoolId = (int) $profile['schoolId'];
                    }
                    if ($school) {
                        $schoolId = $school->id;
                        $organizationId = $school->organization_id;
                    }
                    Student::query()->create([
                        'user_id' => $user->id,
                        'school_id' => $schoolId,
                        'organization_id' => $organizationId,
                        'student_id_number' => $profile['studentNumber'] ?? $profile['studentId'] ?? null,
                        'school_name' => $profile['schoolName'] ?? null,
                        'school_subscription_code' => $data['schoolSubscriptionCode'] ?? null,
                        'course' => $profile['course'] ?? null,
                        'year_level' => $profile['yearLevel'] ?? null,
                    ]);

                    if ($school?->organization) {
                        $this->tenantRoleService->ensureDefaultRoles($school->organization);
                        $this->attachOrganizationMembership(
                            $user,
                            $school->organization,
                            'intern',
                            (string) ($profile['course'] ?? 'Student')
                        );
                    }
                }

                return $user;
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
        $data = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

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
        $data = $request->validate([
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

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

            $user->load('student');
            if ($user->student) {
                $merged = $user->profile ?? [];
                $merged['schoolName'] = $merged['schoolName'] ?? $user->student->school_name;
                $merged['course'] = $merged['course'] ?? $user->student->course;
                $merged['yearLevel'] = $merged['yearLevel'] ?? $user->student->year_level;
                $merged['studentNumber'] = $merged['studentNumber'] ?? $user->student->student_id_number;
                $merged['studentId'] = $merged['studentId'] ?? $user->student->student_id_number;
                if ($user->student->school_id) {
                    $merged['schoolId'] = $merged['schoolId'] ?? (string) $user->student->school_id;
                }
                $user->profile = $merged;
            }

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
        }

        if ($activeOrganization) {
            $profile['organizationId'] = (string) $activeOrganization->id;
            $profile['organizationType'] = $activeOrganization->type;
            $profile['organizationName'] = $activeOrganization->name;
        }

        if ($activeOrganization && $activeOrganization->type === 'school') {
            $tenantSchool = School::query()
                ->where('organization_id', $activeOrganization->id)
                ->first();

            if ($tenantSchool) {
                $official = trim((string) ($tenantSchool->official_school_email ?? ''));
                if ($official !== '' && trim((string) ($profile['officialSchoolEmail'] ?? '')) === '') {
                    $profile['officialSchoolEmail'] = $tenantSchool->official_school_email;
                }
                if (trim((string) ($profile['institutionName'] ?? '')) === '' && $tenantSchool->institution_name) {
                    $profile['institutionName'] = $tenantSchool->institution_name;
                }
                if (trim((string) ($profile['schoolName'] ?? '')) === '' && $tenantSchool->institution_name) {
                    $profile['schoolName'] = $tenantSchool->institution_name;
                }
            }

            $ownerId = (int) ($activeOrganization->owner_user_id ?? 0);
            if ($ownerId > 0) {
                $ownerProfile = User::query()->whereKey($ownerId)->value('profile');
                $ownerCourses = is_array($ownerProfile) ? ($ownerProfile['courses'] ?? null) : null;
                if (is_array($ownerCourses) && $ownerCourses !== []) {
                    $currentCourses = $profile['courses'] ?? null;
                    if (! is_array($currentCourses) || $currentCourses === []) {
                        $profile['courses'] = $ownerCourses;
                    }
                }
            }
        }

        if ($activeOrganization && $activeOrganization->type === 'company') {
            $ownerId = (int) ($activeOrganization->owner_user_id ?? 0);
            if ($ownerId > 0 && $ownerId !== (int) $user->id) {
                $ownerProfile = User::query()->whereKey($ownerId)->value('profile');
                if (is_array($ownerProfile)) {
                    $ownerCourses = $ownerProfile['courses'] ?? null;
                    if (is_array($ownerCourses) && $ownerCourses !== []) {
                        $currentCourses = $profile['courses'] ?? null;
                        if (! is_array($currentCourses) || $currentCourses === []) {
                            $profile['courses'] = $ownerCourses;
                        }
                    }
                    if (trim((string) ($profile['companyName'] ?? '')) === '' && ! empty($ownerProfile['companyName'])) {
                        $profile['companyName'] = (string) $ownerProfile['companyName'];
                    }
                }
            }
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
                    'permissions' => Permission::normalizeOrganizationPermissionKeys($membership->effectivePermissions()),
                ];
            })->values()->all(),
            'isOrganizationOwner' => $activeOrganization
                && (int) ($activeOrganization->owner_user_id ?? 0) === (int) $user->id,
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
}
