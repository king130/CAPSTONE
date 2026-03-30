<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Permission;
use App\Models\Role;
use App\Models\School;
use App\Models\Student;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    public function register(Request $request)
    {
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

            if ($role === 'school' || $role === 'company') {
                $sub = Subscription::query()->create([
                    'plan' => $data['subscriptionPlan'] ?? 'free',
                    'status' => 'pending',
                    'billing_cycle' => $data['billingCycle'] ?? 'monthly',
                ]);

                $organization = Organization::query()->create([
                    'name' => $role === 'school'
                        ? (string) ($profile['institutionName'] ?? $user->name)
                        : (string) ($profile['companyName'] ?? $user->name),
                    'type' => $role,
                    'subscription_id' => $sub->id,
                    'owner_user_id' => $user->id,
                    'settings' => [
                        'purchase_confirmation_required' => true,
                        'delegated_admin_enabled' => true,
                    ],
                    'is_active' => true,
                ]);

                if ($role === 'school') {
                    $schoolRecord = School::query()->create([
                        'user_id' => $user->id,
                        'subscription_id' => $sub->id,
                        'organization_id' => $organization->id,
                        'institution_name' => (string) ($profile['institutionName'] ?? $user->name),
                        'subscription_code' => strtoupper(substr(bin2hex(random_bytes(3)), 0, 6).'-'.substr((string) time(), -4)),
                    ]);

                    $this->attachOrganizationMembership(
                        $user,
                        $organization,
                        'school_admin',
                        (string) ($profile['position'] ?? 'Primary Admin')
                    );
                } else {
                    Company::query()->create([
                        'user_id' => $user->id,
                        'subscription_id' => $sub->id,
                        'organization_id' => $organization->id,
                        'company_name' => (string) ($profile['companyName'] ?? $user->name),
                    ]);

                    $this->attachOrganizationMembership(
                        $user,
                        $organization,
                        'company_admin',
                        (string) ($profile['contactPersonTitle'] ?? 'Primary Admin')
                    );
                }
            }

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
                    $this->attachOrganizationMembership(
                        $user,
                        $school->organization,
                        'student_member',
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
    }

    public function login(Request $request)
    {
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

    /**
     * @return array<string, mixed>
     */
    public function formatUserProfile(User $user): array
    {
        $profile = $user->profile ?? [];
        $primaryMembership = $user->primaryOrganizationMembership();
        $activeOrganization = $primaryMembership?->organization;
        $subscription = $activeOrganization?->subscription;

        if ($user->relationLoaded('student') && $user->student) {
            $s = $user->student;
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

        $appRole = $user->effectiveAppRole();

        return [
            'uid' => (string) $user->id,
            'email' => $user->email,
            'displayName' => $user->name,
            'role' => $appRole,
            'isTemporary' => $user->is_temporary,
            'isActive' => $user->is_active,
            'profileSetupComplete' => $user->profile_setup_complete,
            'mustChangePassword' => $user->must_change_password,
            'profile' => $profile,
            'accessScope' => $user->isPlatformAdmin() ? 'platform' : ($activeOrganization ? 'organization' : 'personal'),
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

    private function attachOrganizationMembership(User $user, Organization $organization, string $roleSlug, string $title = ''): void
    {
        $role = Role::query()->where('slug', $roleSlug)->first();
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
    }
}
