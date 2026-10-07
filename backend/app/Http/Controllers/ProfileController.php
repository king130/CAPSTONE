<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Role;
use App\Models\School;
use App\Models\Student;
use App\Models\Subscription;
use App\Models\User;
use App\Services\TenantRoleService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function __construct(
        private readonly TenantRoleService $tenantRoleService,
    )
    {
    }

    public function update(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        if ($request->exists('subscription')) {
            return response()->json([
                'message' => 'Subscription changes must be made through the billing endpoints.',
                'errors' => [
                    'subscription' => ['Subscription fields are not allowed on the profile endpoint.'],
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'displayName' => ['sometimes', 'string', 'max:255'],
            'profile' => ['sometimes', 'array'],
            'profileSetupComplete' => ['sometimes', 'boolean'],
            'role' => ['sometimes', Rule::in(['student', 'school', 'company', 'guest'])],
        ]);

        if (isset($data['displayName'])) {
            $user->name = $data['displayName'];
        }
        if (isset($data['name'])) {
            $user->name = $data['name'];
        }
        if (isset($data['profile'])) {
            $user->profile = array_merge($user->profile ?? [], $data['profile']);
        }
        if (isset($data['profileSetupComplete'])) {
            $user->profile_setup_complete = $data['profileSetupComplete'];
        }

        if (isset($data['role']) && $user->role === 'guest') {
            if (in_array($data['role'], ['school', 'company'], true)) {
                return response()->json([
                    'message' => 'School and company accounts must be created through registration.',
                ], Response::HTTP_FORBIDDEN);
            }

            if ($data['role'] === 'student') {
                return response()->json([
                    'message' => 'Student accounts are created by your school. Use your school-issued setup link or login credentials.',
                ], Response::HTTP_FORBIDDEN);
            }

            $this->promoteGuestRole($user, $data['role']);
        }

        if (isset($data['profile'])) {
            $this->syncRoleSpecificProfile($user, $data['profile']);
        }

        $user->save();

        $freshUser = $user->fresh([
            'student',
            'company',
            'school',
            'platformRole.permissions',
            'organizationMemberships.role.permissions',
            'organizationMemberships.organization.subscription',
        ]);

        $auth = app(AuthController::class);

        return response()->json($auth->formatUserProfile($freshUser));
    }

    /**
     * @param array<string, mixed> $profile
     */
    private function syncRoleSpecificProfile(User $user, array $profile): void
    {
        $organization = $user->primaryOrganizationMembership()?->organization;

        if ($user->role === 'company' && $user->company) {
            $user->company->fill([
                'company_name' => $profile['companyName'] ?? $user->company->company_name,
                'company_type' => $profile['companyType'] ?? $user->company->company_type,
                'industry_type' => $profile['industryType'] ?? $user->company->industry_type,
                'company_address' => $profile['companyAddress'] ?? $user->company->company_address,
                'company_email' => $profile['companyEmail'] ?? $user->company->company_email,
                'contact_person_name' => $profile['contactPersonName'] ?? $user->company->contact_person_name,
                'contact_person_title' => $profile['contactPersonTitle'] ?? $user->company->contact_person_title,
                'contact_person_email' => $profile['contactPersonEmail'] ?? $user->company->contact_person_email,
                'company_contact_number' => $profile['companyContactNumber'] ?? $profile['contactNumber'] ?? $user->company->company_contact_number,
            ]);
            $user->company->save();

            if ($organization && ! empty($profile['companyName'])) {
                $organization->name = (string) $profile['companyName'];
                $organization->save();
            }
        }

        if ($user->role === 'school' && $user->school) {
            $user->school->fill([
                'institution_name' => $profile['institutionName'] ?? $user->school->institution_name,
                'institution_type' => $profile['institutionType'] ?? $user->school->institution_type,
                'department' => $profile['department'] ?? $user->school->department,
                'position' => $profile['position'] ?? $user->school->position,
                'official_school_email' => $profile['officialSchoolEmail'] ?? $user->school->official_school_email,
                'school_contact_number' => $profile['schoolContactNumber'] ?? $profile['contactNumber'] ?? $user->school->school_contact_number,
                'school_address' => $profile['schoolAddress'] ?? $user->school->school_address,
            ]);
            $user->school->save();

            if ($organization && ! empty($profile['institutionName'])) {
                $organization->name = (string) $profile['institutionName'];
                $organization->save();
            }
        }

        if ($user->role === 'student' && $user->student) {
            // School membership is not client-controlled. Attachment may only occur via
            // validated subscription-code registration or school invitation flows.
            $user->student->fill([
                'student_id_number' => $profile['studentNumber'] ?? $profile['studentId'] ?? $user->student->student_id_number,
                'school_name' => $profile['schoolName'] ?? $user->student->school_name,
                'course' => $profile['course'] ?? $user->student->course,
                'year_level' => $profile['yearLevel'] ?? $user->student->year_level,
                'preferred_field' => $profile['preferredField'] ?? $user->student->preferred_field,
                'contact_number' => $profile['contactNumber'] ?? $user->student->contact_number,
            ]);
            $user->student->save();
        }
    }

    private function promoteGuestRole(User $user, string $newRole): void
    {
        $profile = $user->profile ?? [];

        if ($newRole === 'student') {
            // Student accounts must be school-provisioned. Public guest promotion is blocked upstream.
            return;
        }

        if ($newRole === 'company') {
            if (! $user->company) {
                $sub = Subscription::query()->create([
                    'plan' => 'free',
                    'status' => 'active',
                    'billing_cycle' => 'monthly',
                ]);
                $organization = Organization::query()->create([
                    'name' => (string) ($profile['companyName'] ?? $user->name),
                    'type' => 'company',
                    'subscription_id' => $sub->id,
                    'owner_user_id' => $user->id,
                    'settings' => [
                        'purchase_confirmation_required' => true,
                        'delegated_admin_enabled' => true,
                    ],
                ]);
                $this->tenantRoleService->ensureDefaultRoles($organization);
                Company::query()->create([
                    'user_id' => $user->id,
                    'subscription_id' => $sub->id,
                    'organization_id' => $organization->id,
                    'company_name' => (string) ($profile['companyName'] ?? $user->name),
                ]);
                $this->attachOrganizationMembership($user, $organization, 'company_admin', (string) ($profile['contactPersonTitle'] ?? 'Primary Admin'));
            }
            $user->role = 'company';

            return;
        }

        if ($newRole === 'school') {
            if (! $user->school) {
                $sub = Subscription::query()->create([
                    'plan' => 'free',
                    'status' => 'active',
                    'billing_cycle' => 'monthly',
                ]);
                $organization = Organization::query()->create([
                    'name' => (string) ($profile['institutionName'] ?? $user->name),
                    'type' => 'school',
                    'subscription_id' => $sub->id,
                    'owner_user_id' => $user->id,
                    'settings' => [
                        'purchase_confirmation_required' => true,
                        'delegated_admin_enabled' => true,
                    ],
                ]);
                $this->tenantRoleService->ensureDefaultRoles($organization);
                School::query()->create([
                    'user_id' => $user->id,
                    'subscription_id' => $sub->id,
                    'organization_id' => $organization->id,
                    'institution_name' => (string) ($profile['institutionName'] ?? $user->name),
                    'subscription_code' => strtoupper(substr(bin2hex(random_bytes(3)), 0, 6).'-'.substr((string) time(), -4)),
                ]);
                $this->attachOrganizationMembership($user, $organization, 'school_admin', (string) ($profile['position'] ?? 'Primary Admin'));
            }
            $user->role = 'school';
        }
    }

    private function attachOrganizationMembership(User $user, Organization $organization, string $roleSlug, string $title = ''): void
    {
        $this->tenantRoleService->ensureDefaultRoles($organization);

        $role = Role::query()
            ->where('tenant_id', $organization->id)
            ->where('slug', $roleSlug)
            ->first();

        if (! $role) {
            $role = Role::query()->whereNull('tenant_id')->where('slug', $roleSlug)->first();
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
