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
use App\Support\CaviteAddress;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
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

        if ($user->role === 'student') {
            return $this->updateStudentSelfServiceProfile($user, $data);
        }

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

        return $this->profileResponse($user);
    }

    /**
     * Students may only self-update contact details. School-managed identity/enrollment
     * fields are rejected with 422 so clients cannot treat forbidden writes as success.
     *
     * @param  array<string, mixed>  $data
     */
    private function updateStudentSelfServiceProfile(User $user, array $data)
    {
        $errors = [];

        foreach (['name', 'displayName', 'role'] as $topLevelKey) {
            if (array_key_exists($topLevelKey, $data)) {
                $errors[$topLevelKey] = [
                    'This field is managed by your school and cannot be changed from your profile.',
                ];
            }
        }

        $profile = is_array($data['profile'] ?? null) ? $data['profile'] : [];
        $forbiddenProfileKeys = [
            'studentNumber',
            'studentId',
            'student_id_number',
            'course',
            'yearLevel',
            'year_level',
            'schoolName',
            'school_name',
            'schoolId',
            'school_id',
            'preferredField',
            'preferred_field',
            'organizationId',
            'organization_id',
            'schoolSubscriptionCode',
            'school_subscription_code',
            'email',
            'subscription',
            'companyName',
            'institutionName',
        ];

        foreach ($forbiddenProfileKeys as $key) {
            if (array_key_exists($key, $profile)) {
                $errors['profile.'.$key] = [
                    'This field is managed by your school and cannot be changed from your profile.',
                ];
            }
        }

        if ($errors !== []) {
            return response()->json([
                'message' => 'Student identity and enrollment details are managed by your school.',
                'errors' => $errors,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (array_key_exists('contactNumber', $profile) && $user->student) {
            $contactNumber = $profile['contactNumber'];
            $user->student->contact_number = is_string($contactNumber) || is_numeric($contactNumber)
                ? (string) $contactNumber
                : $user->student->contact_number;
            $user->student->save();

            $user->profile = array_merge($user->profile ?? [], [
                'contactNumber' => $user->student->contact_number,
            ]);
        }

        if (isset($data['profileSetupComplete'])) {
            $user->profile_setup_complete = $data['profileSetupComplete'];
        }

        $user->save();

        return $this->profileResponse($user);
    }

    private function profileResponse(User $user)
    {
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
        $hostCompany = $user->organizationCompany() ?? $user->company;
        $isHostWorkspace = $user->effectiveAppRole() === 'company'
            || \App\Support\OrganizationTypes::isHostType($organization?->type)
            || in_array($user->role, ['company', 'municipal_hall'], true);

        if ($isHostWorkspace && $hostCompany) {
            $orgLevelKeys = [
                'companyName',
                'officialName',
                'companyType',
                'industryType',
                'companyAddress',
                'officialAddress',
                'companyEmail',
                'officialEmail',
                'contactPersonName',
                'representativeName',
                'contactPersonTitle',
                'representativePosition',
                'contactPersonEmail',
                'companyContactNumber',
                'locationArea',
                'cityMunicipality',
                'lguType',
            ];
            $touchesOrgLevel = collect($orgLevelKeys)->contains(fn (string $key) => array_key_exists($key, $profile));
            if ($touchesOrgLevel && ! $this->canManageOrganizationProfile($user)) {
                throw ValidationException::withMessages([
                    'profile' => ['Only organization administrators can update organization-level settings.'],
                ]);
            }

            $canonicalAddress = CaviteAddress::canonicalFromProfile($profile, $hostCompany->company_address);
            if (isset($canonicalAddress['error'])) {
                throw ValidationException::withMessages([
                    'profile.companyAddress' => [$canonicalAddress['error']],
                ]);
            }

            $nextName = $profile['officialName']
                ?? $profile['companyName']
                ?? $hostCompany->company_name;

            $hostCompany->fill([
                'company_name' => $nextName,
                'company_type' => $profile['companyType'] ?? $hostCompany->company_type,
                'lgu_type' => array_key_exists('lguType', $profile)
                    ? $profile['lguType']
                    : $hostCompany->lgu_type,
                'industry_type' => $profile['industryType'] ?? $hostCompany->industry_type,
                'company_address' => $canonicalAddress['address'] ?? $hostCompany->company_address,
                'city_municipality' => $canonicalAddress['city']
                    ?? ($profile['locationArea'] ?? $profile['cityMunicipality'] ?? $hostCompany->city_municipality),
                'company_email' => $profile['companyEmail']
                    ?? $profile['officialEmail']
                    ?? $hostCompany->company_email,
                'contact_person_name' => $profile['contactPersonName']
                    ?? $profile['representativeName']
                    ?? $hostCompany->contact_person_name,
                'contact_person_title' => $profile['contactPersonTitle']
                    ?? $profile['representativePosition']
                    ?? $hostCompany->contact_person_title,
                'contact_person_email' => $profile['contactPersonEmail']
                    ?? $profile['officialEmail']
                    ?? $hostCompany->contact_person_email,
                'company_contact_number' => $profile['companyContactNumber']
                    ?? $profile['contactNumber']
                    ?? $hostCompany->company_contact_number,
            ]);
            $hostCompany->save();

            if ($organization && $nextName) {
                $organization->name = (string) $nextName;
                $organization->save();
            }

            if ($organization && isset($canonicalAddress['address'])) {
                CaviteAddress::rememberOnOrganization(
                    $organization,
                    $canonicalAddress['address'],
                    $canonicalAddress['city'] ?? null,
                );
            }
        }

        if ($user->role === 'school' && $user->school) {
            $canonicalAddress = CaviteAddress::canonicalFromProfile($profile, $user->school->school_address);
            if (isset($canonicalAddress['error'])) {
                throw ValidationException::withMessages([
                    'profile.schoolAddress' => [$canonicalAddress['error']],
                ]);
            }

            $user->school->fill([
                'institution_name' => $profile['institutionName'] ?? $user->school->institution_name,
                'institution_type' => $profile['institutionType'] ?? $user->school->institution_type,
                'department' => $profile['department'] ?? $user->school->department,
                'position' => $profile['position'] ?? $user->school->position,
                'official_school_email' => $profile['officialSchoolEmail'] ?? $user->school->official_school_email,
                'school_contact_number' => $profile['schoolContactNumber'] ?? $profile['contactNumber'] ?? $user->school->school_contact_number,
                'school_address' => $canonicalAddress['address'] ?? $user->school->school_address,
            ]);
            $user->school->save();

            if ($organization && ! empty($profile['institutionName'])) {
                $organization->name = (string) $profile['institutionName'];
                $organization->save();
            }

            if ($organization && isset($canonicalAddress['address'])) {
                CaviteAddress::rememberOnOrganization(
                    $organization,
                    $canonicalAddress['address'],
                    $canonicalAddress['city'] ?? null,
                );
            }
        }

        // Student self-service identity/enrollment updates are handled exclusively by
        // updateStudentSelfServiceProfile(); this method must not write student fields.
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

    private function canManageOrganizationProfile(User $user): bool
    {
        $user->loadMissing(['company.organization', 'school.organization']);

        $organization = $user->primaryOrganizationMembership()?->organization
            ?? $user->activeOrganization()
            ?? $user->company?->organization
            ?? $user->school?->organization;

        if ($organization && (int) $organization->owner_user_id === (int) $user->id) {
            return true;
        }

        return app(\App\Services\PermissionGate::class)->userCanAny($user, [
            'org.manage_profile',
            'manage_profile',
        ]);
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
