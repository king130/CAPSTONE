<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Role;
use App\Models\School;
use App\Models\Subscription;
use App\Models\User;
use App\Support\CaviteAddress;
use App\Support\OrganizationTypes;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrganizationAccountProvisioner
{
    public function __construct(private readonly TenantRoleService $tenantRoleService)
    {
    }

    /**
     * @param array<string, mixed> $input
     * @return array{user:User, organization:Organization, subscription:Subscription, temporaryPassword:?string}
     */
    public function create(array $input): array
    {
        return DB::transaction(function () use ($input) {
            $role = (string) $input['role'];
            $profile = is_array($input['profile'] ?? null) ? $input['profile'] : [];
            $temporaryPassword = $input['temporaryPassword'] ?? null;
            $selectedPlan = strtolower((string) ($input['subscriptionPlan'] ?? 'standard'));
            if ($selectedPlan === 'free') {
                // Free is legacy and not selectable for new organizations.
                $selectedPlan = 'standard';
            }
            $billingCycle = (string) ($input['billingCycle'] ?? 'monthly');
            $fullName = trim((string) $input['fullName']);
            $email = strtolower(trim((string) $input['email']));

            if ($role === OrganizationTypes::MUNICIPAL_HALL) {
                $this->assertMunicipalHallProfile($profile);
                $this->assertMunicipalHallNotDuplicate($profile);
            }

            $orgName = match ($role) {
                'school' => (string) ($profile['institutionName'] ?? $fullName),
                OrganizationTypes::MUNICIPAL_HALL => (string) ($profile['officialName'] ?? $profile['companyName'] ?? $fullName),
                default => (string) ($profile['companyName'] ?? $fullName),
            };

            $user = User::query()->create([
                'name' => $fullName,
                'email' => $email,
                'password' => (string) ($input['password'] ?? $temporaryPassword),
                'role' => $role === OrganizationTypes::MUNICIPAL_HALL ? OrganizationTypes::MUNICIPAL_HALL : $role,
                'profile' => $profile,
                'is_active' => true,
                'is_temporary' => $temporaryPassword !== null,
                'must_change_password' => $temporaryPassword !== null,
                'profile_setup_complete' => true,
            ]);

            // Every new org starts a time-limited trial (features of the trial plan, default Standard).
            $subscription = Subscription::query()->create([
                'plan' => $selectedPlan,
                'status' => 'trialing',
                'billing_cycle' => $billingCycle,
            ]);

            $organization = Organization::query()->create([
                'name' => $orgName,
                'type' => $role,
                'subscription_id' => $subscription->id,
                'owner_user_id' => $user->id,
                'settings' => [
                    'purchase_confirmation_required' => true,
                    'delegated_admin_enabled' => true,
                ],
                'is_active' => true,
            ]);

            $subscription = app(\App\Services\SubscriptionAccessService::class)
                ->startTrial($subscription, $organization, $user);

            $canonical = CaviteAddress::canonicalFromProfile($profile, null);
            if (! empty($canonical['error'])) {
                throw ValidationException::withMessages([
                    'profile.locationArea' => [$canonical['error']],
                ]);
            }
            CaviteAddress::rememberOnOrganization(
                $organization,
                $canonical['address'] ?? null,
                $canonical['city'] ?? null
            );

            $this->tenantRoleService->ensureDefaultRoles($organization);

            if ($role === 'school') {
                School::query()->create([
                    'user_id' => $user->id,
                    'subscription_id' => $subscription->id,
                    'organization_id' => $organization->id,
                    'institution_name' => (string) ($profile['institutionName'] ?? $user->name),
                    'position' => (string) ($profile['position'] ?? $profile['contactPersonName'] ?? 'Primary Admin'),
                    'school_address' => $profile['schoolAddress'] ?? ($canonical['address'] ?? null),
                    'official_school_email' => $profile['officialSchoolEmail'] ?? $user->email,
                    'school_contact_number' => $profile['schoolContactNumber'] ?? null,
                    'subscription_code' => strtoupper(substr(bin2hex(random_bytes(3)), 0, 6).'-'.substr((string) time(), -4)),
                ]);

                $this->attachOrganizationMembership(
                    $user,
                    $organization,
                    'school_admin',
                    (string) ($profile['position'] ?? $profile['contactPersonName'] ?? 'Primary Admin')
                );
            } else {
                // Company and Municipal Hall share the companies host profile (placements / DTR FKs).
                $city = $canonical['city']
                    ?? (trim((string) ($profile['locationArea'] ?? $profile['cityMunicipality'] ?? $profile['addressCity'] ?? '')) ?: null);

                Company::query()->create([
                    'user_id' => $user->id,
                    'subscription_id' => $subscription->id,
                    'organization_id' => $organization->id,
                    'company_name' => $orgName,
                    'company_type' => $role === OrganizationTypes::MUNICIPAL_HALL ? 'municipal_hall' : ($profile['companyType'] ?? null),
                    'lgu_type' => $role === OrganizationTypes::MUNICIPAL_HALL
                        ? (string) ($profile['lguType'] ?? '')
                        : null,
                    'company_address' => $profile['companyAddress']
                        ?? $profile['officialAddress']
                        ?? ($canonical['address'] ?? null),
                    'city_municipality' => $city,
                    'company_email' => $profile['companyEmail']
                        ?? $profile['officialEmail']
                        ?? $user->email,
                    'industry_type' => $role === OrganizationTypes::MUNICIPAL_HALL
                        ? 'Local Government'
                        : ($profile['industryType'] ?? null),
                    'contact_person_name' => $profile['contactPersonName']
                        ?? $profile['representativeName']
                        ?? $user->name,
                    'contact_person_title' => $profile['contactPersonTitle']
                        ?? $profile['representativePosition']
                        ?? 'Primary Admin',
                    'contact_person_email' => $profile['contactPersonEmail']
                        ?? $profile['officialEmail']
                        ?? $user->email,
                    'company_contact_number' => $profile['companyContactNumber']
                        ?? $profile['contactNumber']
                        ?? null,
                    'verification_status' => 'pending',
                ]);

                $this->attachOrganizationMembership(
                    $user,
                    $organization,
                    'company_admin',
                    (string) ($profile['contactPersonTitle']
                        ?? $profile['representativePosition']
                        ?? 'Primary Admin')
                );
            }

            return [
                'user' => $user,
                'organization' => $organization,
                'subscription' => $subscription,
                'temporaryPassword' => $temporaryPassword,
            ];
        });
    }

    public function generateTemporaryPassword(): string
    {
        return 'Temp@'.strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
    }

    /**
     * @param array<string, mixed> $profile
     */
    private function assertMunicipalHallProfile(array $profile): void
    {
        $officialName = trim((string) ($profile['officialName'] ?? $profile['companyName'] ?? ''));
        $lguType = strtolower(trim((string) ($profile['lguType'] ?? '')));
        $representative = trim((string) ($profile['representativeName'] ?? $profile['contactPersonName'] ?? ''));
        $position = trim((string) ($profile['representativePosition'] ?? $profile['contactPersonTitle'] ?? ''));
        $city = trim((string) ($profile['locationArea'] ?? $profile['cityMunicipality'] ?? $profile['addressCity'] ?? ''));

        $errors = [];
        if ($officialName === '') {
            $errors['profile.officialName'] = ['Official name of the municipality or local government office is required.'];
        }
        if ($city === '' || ! CaviteAddress::isKnownCity($city)) {
            $errors['profile.locationArea'] = ['A Cavite city or municipality is required.'];
        }
        if (! in_array($lguType, OrganizationTypes::lguTypes(), true)) {
            $errors['profile.lguType'] = ['LGU type must be municipality, city, or other office.'];
        }
        if ($representative === '') {
            $errors['profile.representativeName'] = ['Authorized representative name is required.'];
        }
        if ($position === '') {
            $errors['profile.representativePosition'] = ['Authorized representative position is required.'];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param array<string, mixed> $profile
     */
    private function assertMunicipalHallNotDuplicate(array $profile): void
    {
        $officialName = trim((string) ($profile['officialName'] ?? $profile['companyName'] ?? ''));
        $city = trim((string) ($profile['locationArea'] ?? $profile['cityMunicipality'] ?? $profile['addressCity'] ?? ''));
        if ($officialName === '' || $city === '') {
            return;
        }

        $normalizedName = mb_strtolower(preg_replace('/\s+/', ' ', $officialName) ?? $officialName);
        $normalizedCity = mb_strtolower(preg_replace('/\s+/', ' ', $city) ?? $city);

        $exists = Company::query()
            ->whereHas('organization', fn ($q) => $q->where('type', OrganizationTypes::MUNICIPAL_HALL))
            ->get()
            ->contains(function (Company $company) use ($normalizedName, $normalizedCity) {
                $name = mb_strtolower(trim(preg_replace('/\s+/', ' ', (string) $company->company_name) ?? ''));
                $city = mb_strtolower(trim(preg_replace('/\s+/', ' ', (string) ($company->city_municipality ?? '')) ?? ''));
                $address = mb_strtolower((string) ($company->company_address ?? ''));

                return $name === $normalizedName
                    && ($city === $normalizedCity || str_contains($address, $normalizedCity));
            });

        if ($exists) {
            throw ValidationException::withMessages([
                'profile.officialName' => [
                    'A Municipal Hall account with this official name already exists for that city/municipality.',
                ],
            ]);
        }
    }

    private function attachOrganizationMembership(User $user, Organization $organization, string $roleSlug, string $title = ''): void
    {
        $role = Role::query()
            ->where('tenant_id', $organization->id)
            ->where('slug', $roleSlug)
            ->first();

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
