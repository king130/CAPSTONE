<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Contract;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Permission;
use App\Models\Role;
use App\Models\School;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AgreementDuplicateGuardTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{
     *   schoolUser: User,
     *   school: School,
     *   schoolOrg: Organization,
     *   companyUser: User,
     *   company: Company,
     *   companyOrg: Organization
     * }
     */
    private function createPair(): array
    {
        foreach (['org.manage_agreements', 'org.approve_agreements'] as $key) {
            Permission::query()->updateOrCreate(
                ['key' => $key],
                ['scope' => 'organization', 'description' => $key]
            );
        }

        $schoolUser = User::factory()->create([
            'role' => 'school',
            'email' => 'school.'.uniqid('', true).'@example.com',
            'is_active' => true,
        ]);
        $schoolSub = Subscription::query()->create([
            'plan' => 'standard',
            'status' => 'active',
            'billing_cycle' => 'monthly',
        ]);
        $schoolOrg = Organization::query()->create([
            'name' => 'Dup Guard School Org',
            'type' => 'school',
            'subscription_id' => $schoolSub->id,
            'owner_user_id' => $schoolUser->id,
            'settings' => [],
            'is_active' => true,
        ]);
        $schoolRole = Role::query()->create([
            'tenant_id' => $schoolOrg->id,
            'name' => 'School Admin',
            'slug' => 'school_admin_'.uniqid(),
            'scope' => 'organization',
            'organization_type' => 'school',
        ]);
        $schoolRole->permissions()->sync(
            Permission::query()->whereIn('key', ['org.manage_agreements', 'org.approve_agreements'])->pluck('id')
        );
        OrganizationMembership::query()->create([
            'organization_id' => $schoolOrg->id,
            'user_id' => $schoolUser->id,
            'role_id' => $schoolRole->id,
            'status' => 'active',
            'title' => 'Admin',
        ]);
        $schoolUser->forceFill([
            'tenant_id' => $schoolOrg->id,
            'role_id' => $schoolRole->id,
        ])->save();
        $school = School::query()->create([
            'user_id' => $schoolUser->id,
            'organization_id' => $schoolOrg->id,
            'institution_name' => 'Dup Guard School',
            'subscription_code' => 'DGS-'.strtoupper(substr(uniqid(), -4)),
            'verification_status' => 'approved',
        ]);

        $companyUser = User::factory()->create([
            'role' => 'company',
            'email' => 'company.'.uniqid('', true).'@example.com',
            'is_active' => true,
        ]);
        $companySub = Subscription::query()->create([
            'plan' => 'standard',
            'status' => 'active',
            'billing_cycle' => 'monthly',
        ]);
        $companyOrg = Organization::query()->create([
            'name' => 'Dup Guard Company Org',
            'type' => 'company',
            'subscription_id' => $companySub->id,
            'owner_user_id' => $companyUser->id,
            'settings' => [],
            'is_active' => true,
        ]);
        $companyRole = Role::query()->create([
            'tenant_id' => $companyOrg->id,
            'name' => 'Company Admin',
            'slug' => 'company_admin_'.uniqid(),
            'scope' => 'organization',
            'organization_type' => 'company',
        ]);
        $companyRole->permissions()->sync(
            Permission::query()->whereIn('key', ['org.manage_agreements', 'org.approve_agreements'])->pluck('id')
        );
        OrganizationMembership::query()->create([
            'organization_id' => $companyOrg->id,
            'user_id' => $companyUser->id,
            'role_id' => $companyRole->id,
            'status' => 'active',
            'title' => 'Admin',
        ]);
        $companyUser->forceFill([
            'tenant_id' => $companyOrg->id,
            'role_id' => $companyRole->id,
        ])->save();
        $company = Company::query()->create([
            'user_id' => $companyUser->id,
            'organization_id' => $companyOrg->id,
            'company_name' => 'Dup Guard Company',
            'verification_status' => 'approved',
        ]);

        return compact('schoolUser', 'school', 'schoolOrg', 'companyUser', 'company', 'companyOrg');
    }

    /**
     * @return array{companyUser: User, company: Company, companyOrg: Organization}
     */
    private function createExtraCompany(): array
    {
        foreach (['org.manage_agreements', 'org.approve_agreements'] as $key) {
            Permission::query()->updateOrCreate(
                ['key' => $key],
                ['scope' => 'organization', 'description' => $key]
            );
        }

        $companyUser = User::factory()->create([
            'role' => 'company',
            'email' => 'company2.'.uniqid('', true).'@example.com',
            'is_active' => true,
        ]);
        $companySub = Subscription::query()->create([
            'plan' => 'standard',
            'status' => 'active',
            'billing_cycle' => 'monthly',
        ]);
        $companyOrg = Organization::query()->create([
            'name' => 'Dup Guard Extra Company Org',
            'type' => 'company',
            'subscription_id' => $companySub->id,
            'owner_user_id' => $companyUser->id,
            'settings' => [],
            'is_active' => true,
        ]);
        $companyRole = Role::query()->create([
            'tenant_id' => $companyOrg->id,
            'name' => 'Company Admin',
            'slug' => 'company_admin_'.uniqid(),
            'scope' => 'organization',
            'organization_type' => 'company',
        ]);
        $companyRole->permissions()->sync(
            Permission::query()->whereIn('key', ['org.manage_agreements', 'org.approve_agreements'])->pluck('id')
        );
        OrganizationMembership::query()->create([
            'organization_id' => $companyOrg->id,
            'user_id' => $companyUser->id,
            'role_id' => $companyRole->id,
            'status' => 'active',
            'title' => 'Admin',
        ]);
        $companyUser->forceFill([
            'tenant_id' => $companyOrg->id,
            'role_id' => $companyRole->id,
        ])->save();
        $company = Company::query()->create([
            'user_id' => $companyUser->id,
            'organization_id' => $companyOrg->id,
            'company_name' => 'Dup Guard Extra Company',
            'verification_status' => 'approved',
        ]);

        return compact('companyUser', 'company', 'companyOrg');
    }

    /**
     * @return array{schoolUser: User, school: School, schoolOrg: Organization}
     */
    private function createExtraSchool(): array
    {
        foreach (['org.manage_agreements', 'org.approve_agreements'] as $key) {
            Permission::query()->updateOrCreate(
                ['key' => $key],
                ['scope' => 'organization', 'description' => $key]
            );
        }

        $schoolUser = User::factory()->create([
            'role' => 'school',
            'email' => 'school2.'.uniqid('', true).'@example.com',
            'is_active' => true,
        ]);
        $schoolSub = Subscription::query()->create([
            'plan' => 'standard',
            'status' => 'active',
            'billing_cycle' => 'monthly',
        ]);
        $schoolOrg = Organization::query()->create([
            'name' => 'Dup Guard Extra School Org',
            'type' => 'school',
            'subscription_id' => $schoolSub->id,
            'owner_user_id' => $schoolUser->id,
            'settings' => [],
            'is_active' => true,
        ]);
        $schoolRole = Role::query()->create([
            'tenant_id' => $schoolOrg->id,
            'name' => 'School Admin',
            'slug' => 'school_admin_'.uniqid(),
            'scope' => 'organization',
            'organization_type' => 'school',
        ]);
        $schoolRole->permissions()->sync(
            Permission::query()->whereIn('key', ['org.manage_agreements', 'org.approve_agreements'])->pluck('id')
        );
        OrganizationMembership::query()->create([
            'organization_id' => $schoolOrg->id,
            'user_id' => $schoolUser->id,
            'role_id' => $schoolRole->id,
            'status' => 'active',
            'title' => 'Admin',
        ]);
        $schoolUser->forceFill([
            'tenant_id' => $schoolOrg->id,
            'role_id' => $schoolRole->id,
        ])->save();
        $school = School::query()->create([
            'user_id' => $schoolUser->id,
            'organization_id' => $schoolOrg->id,
            'institution_name' => 'Dup Guard Extra School',
            'subscription_code' => 'DGE-'.strtoupper(substr(uniqid(), -4)),
            'verification_status' => 'approved',
        ]);

        return compact('schoolUser', 'school', 'schoolOrg');
    }

    public function test_active_agreement_blocks_new_school_request(): void
    {
        $pair = $this->createPair();
        Contract::query()->create([
            'school_user_id' => $pair['schoolUser']->id,
            'school_name' => $pair['school']->institution_name,
            'company_user_id' => $pair['companyUser']->id,
            'company_name' => $pair['company']->company_name,
            'requested_by_role' => 'school',
            'requested_by_user_id' => $pair['schoolUser']->id,
            'partner_user_id' => $pair['companyUser']->id,
            'status' => 'active',
            'subject' => 'Existing Active MOA',
        ]);

        Sanctum::actingAs($pair['schoolUser']);
        $before = Contract::query()->count();
        $response = $this->postJson('/api/agreements', [
            'requestedByRole' => 'school',
            'subject' => 'Duplicate Active Attempt',
            'companyId' => $pair['companyUser']->id,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['partner']);
        $this->assertStringContainsString('active agreement', (string) $response->json('errors.partner.0'));
        $this->assertSame($before, Contract::query()->count());
    }

    public function test_active_agreement_blocks_new_company_request(): void
    {
        $pair = $this->createPair();
        Contract::query()->create([
            'school_user_id' => $pair['schoolUser']->id,
            'school_name' => $pair['school']->institution_name,
            'company_user_id' => $pair['companyUser']->id,
            'company_name' => $pair['company']->company_name,
            'requested_by_role' => 'company',
            'requested_by_user_id' => $pair['companyUser']->id,
            'partner_user_id' => $pair['schoolUser']->id,
            'status' => 'active',
            'subject' => 'Existing Active MOA',
        ]);

        Sanctum::actingAs($pair['companyUser']);
        $response = $this->postJson('/api/agreements', [
            'requestedByRole' => 'company',
            'subject' => 'Duplicate Active From Company',
            'schoolId' => $pair['schoolUser']->id,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['partner']);
        $this->assertStringContainsString('active agreement', (string) $response->json('errors.partner.0'));
    }

    public function test_pending_agreement_blocks_duplicate_from_either_side(): void
    {
        $pair = $this->createPair();

        Sanctum::actingAs($pair['schoolUser']);
        $this->postJson('/api/agreements', [
            'requestedByRole' => 'school',
            'subject' => 'First Pending MOA',
            'companyId' => $pair['companyUser']->id,
        ])->assertCreated();

        $before = Contract::query()->count();

        Sanctum::actingAs($pair['schoolUser']);
        $schoolDup = $this->postJson('/api/agreements', [
            'requestedByRole' => 'school',
            'subject' => 'School Duplicate Pending',
            'companyId' => $pair['companyUser']->id,
        ]);
        $schoolDup->assertStatus(422);
        $schoolDup->assertJsonValidationErrors(['partner']);
        $this->assertStringContainsString('pending contract request', (string) $schoolDup->json('errors.partner.0'));

        Sanctum::actingAs($pair['companyUser']);
        $companyDup = $this->postJson('/api/agreements', [
            'requestedByRole' => 'company',
            'subject' => 'Company Duplicate Pending',
            'schoolId' => $pair['schoolUser']->id,
        ]);
        $companyDup->assertStatus(422);
        $companyDup->assertJsonValidationErrors(['partner']);
        $this->assertSame($before, Contract::query()->count());
    }

    public function test_school_can_request_agreement_with_unrelated_company(): void
    {
        $pair = $this->createPair();
        $extra = $this->createExtraCompany();

        Contract::query()->create([
            'school_user_id' => $pair['schoolUser']->id,
            'school_name' => $pair['school']->institution_name,
            'company_user_id' => $pair['companyUser']->id,
            'company_name' => $pair['company']->company_name,
            'requested_by_role' => 'school',
            'requested_by_user_id' => $pair['schoolUser']->id,
            'partner_user_id' => $pair['companyUser']->id,
            'status' => 'active',
            'subject' => 'Existing Pair MOA',
        ]);

        Sanctum::actingAs($pair['schoolUser']);
        $response = $this->postJson('/api/agreements', [
            'requestedByRole' => 'school',
            'subject' => 'New Partner MOA',
            'companyId' => $extra['companyUser']->id,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'pending');
        $response->assertJsonPath('data.companyId', (string) $extra['companyUser']->id);
    }

    public function test_company_can_request_agreement_with_unrelated_school(): void
    {
        $pair = $this->createPair();
        $extra = $this->createExtraSchool();

        Contract::query()->create([
            'school_user_id' => $pair['schoolUser']->id,
            'school_name' => $pair['school']->institution_name,
            'company_user_id' => $pair['companyUser']->id,
            'company_name' => $pair['company']->company_name,
            'requested_by_role' => 'company',
            'requested_by_user_id' => $pair['companyUser']->id,
            'partner_user_id' => $pair['schoolUser']->id,
            'status' => 'active',
            'subject' => 'Existing Pair MOA',
        ]);

        Sanctum::actingAs($pair['companyUser']);
        $response = $this->postJson('/api/agreements', [
            'requestedByRole' => 'company',
            'subject' => 'New School Partner MOA',
            'schoolId' => $extra['schoolUser']->id,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'pending');
        $response->assertJsonPath('data.schoolId', (string) $extra['schoolUser']->id);
    }

    public function test_acceptance_and_rejection_semantics_unchanged(): void
    {
        $pair = $this->createPair();

        Sanctum::actingAs($pair['schoolUser']);
        $created = $this->postJson('/api/agreements', [
            'requestedByRole' => 'school',
            'subject' => 'Accept Reject Semantics',
            'companyId' => $pair['companyUser']->id,
        ]);
        $created->assertCreated();
        $agreementId = (string) $created->json('data.id');

        Sanctum::actingAs($pair['companyUser']);
        $this->patchJson('/api/agreements/'.$agreementId.'/accept')
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        $secondPair = $this->createExtraCompany();
        Sanctum::actingAs($pair['schoolUser']);
        $pending = $this->postJson('/api/agreements', [
            'requestedByRole' => 'school',
            'subject' => 'Reject Semantics',
            'companyId' => $secondPair['companyUser']->id,
        ]);
        $pending->assertCreated();
        $pendingId = (string) $pending->json('data.id');

        Sanctum::actingAs($secondPair['companyUser']);
        $this->patchJson('/api/agreements/'.$pendingId.'/reject', [
            'reason' => 'Not a fit',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');
    }
}
