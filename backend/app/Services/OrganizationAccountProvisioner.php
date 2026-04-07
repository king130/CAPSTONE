<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Role;
use App\Models\School;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class OrganizationAccountProvisioner
{
    public function __construct(private readonly TenantRoleService $tenantRoleService)
    {
    }

    /**
     * @param array<string, mixed> $profile
     * @return array{user:User, organization:Organization, subscription:Subscription, temporaryPassword:?string}
     */
    public function create(array $input): array
    {
        return DB::transaction(function () use ($input) {
            $role = (string) $input['role'];
            $profile = is_array($input['profile'] ?? null) ? $input['profile'] : [];
            $temporaryPassword = $input['temporaryPassword'] ?? null;
            $selectedPlan = strtolower((string) ($input['subscriptionPlan'] ?? 'free'));
            $billingCycle = (string) ($input['billingCycle'] ?? 'monthly');
            $fullName = trim((string) $input['fullName']);
            $email = strtolower(trim((string) $input['email']));

            $user = User::query()->create([
                'name' => $fullName,
                'email' => $email,
                'password' => (string) ($input['password'] ?? $temporaryPassword),
                'role' => $role,
                'profile' => $profile,
                'is_active' => true,
                'is_temporary' => $temporaryPassword !== null,
                'must_change_password' => $temporaryPassword !== null,
                'profile_setup_complete' => true,
            ]);

            $subscription = Subscription::query()->create([
                'plan' => $selectedPlan,
                'status' => $selectedPlan === 'free' ? 'active' : 'pending',
                'billing_cycle' => $billingCycle,
            ]);

            $organization = Organization::query()->create([
                'name' => $role === 'school'
                    ? (string) ($profile['institutionName'] ?? $user->name)
                    : (string) ($profile['companyName'] ?? $user->name),
                'type' => $role,
                'subscription_id' => $subscription->id,
                'owner_user_id' => $user->id,
                'settings' => [
                    'purchase_confirmation_required' => true,
                    'delegated_admin_enabled' => true,
                ],
                'is_active' => true,
            ]);

            $this->tenantRoleService->ensureDefaultRoles($organization);

            if ($role === 'school') {
                School::query()->create([
                    'user_id' => $user->id,
                    'subscription_id' => $subscription->id,
                    'organization_id' => $organization->id,
                    'institution_name' => (string) ($profile['institutionName'] ?? $user->name),
                    'position' => (string) ($profile['position'] ?? $profile['contactPersonName'] ?? 'Primary Admin'),
                    'school_address' => $profile['schoolAddress'] ?? null,
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
                Company::query()->create([
                    'user_id' => $user->id,
                    'subscription_id' => $subscription->id,
                    'organization_id' => $organization->id,
                    'company_name' => (string) ($profile['companyName'] ?? $user->name),
                    'company_address' => $profile['companyAddress'] ?? null,
                    'company_email' => $profile['companyEmail'] ?? $user->email,
                    'industry_type' => $profile['industryType'] ?? null,
                    'contact_person_name' => $profile['contactPersonName'] ?? $user->name,
                    'contact_person_title' => $profile['contactPersonTitle'] ?? 'Primary Admin',
                    'contact_person_email' => $profile['contactPersonEmail'] ?? $user->email,
                    'company_contact_number' => $profile['companyContactNumber'] ?? null,
                ]);

                $this->attachOrganizationMembership(
                    $user,
                    $organization,
                    'company_admin',
                    (string) ($profile['contactPersonTitle'] ?? 'Primary Admin')
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
