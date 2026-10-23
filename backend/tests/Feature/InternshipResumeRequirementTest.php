<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Contract;
use App\Models\Internship;
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

/**
 * Phase E: resume is no longer a posting requirement.
 * Full endorsement coverage is in InternshipEndorsementRequirementTest.
 */
class InternshipResumeRequirementTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_resume_flag_is_forced_off_on_create_and_ignored_for_checklist(): void
    {
        Permission::query()->updateOrCreate(
            ['key' => 'org.manage_internships'],
            ['scope' => 'organization', 'description' => 'Create and update internships']
        );

        $schoolUser = User::factory()->create(['role' => 'school', 'is_active' => true]);
        $schoolSub = Subscription::query()->create(['plan' => 'standard', 'status' => 'active', 'billing_cycle' => 'monthly']);
        $schoolOrg = Organization::query()->create([
            'name' => 'Resume School',
            'type' => 'school',
            'subscription_id' => $schoolSub->id,
            'owner_user_id' => $schoolUser->id,
            'is_active' => true,
        ]);
        $school = School::query()->create([
            'user_id' => $schoolUser->id,
            'organization_id' => $schoolOrg->id,
            'institution_name' => 'Resume School',
            'subscription_code' => 'RSM-'.strtoupper(substr(uniqid(), -4)),
            'verification_status' => 'approved',
        ]);

        $companyUser = User::factory()->create(['role' => 'company', 'is_active' => true]);
        $companySub = Subscription::query()->create(['plan' => 'standard', 'status' => 'active', 'billing_cycle' => 'monthly']);
        $companyOrg = Organization::query()->create([
            'name' => 'Resume Company',
            'type' => 'company',
            'subscription_id' => $companySub->id,
            'owner_user_id' => $companyUser->id,
            'is_active' => true,
        ]);
        $role = Role::query()->create([
            'tenant_id' => $companyOrg->id,
            'name' => 'Company Admin',
            'slug' => 'company_admin_'.uniqid(),
            'scope' => 'organization',
            'organization_type' => 'company',
        ]);
        $role->permissions()->sync(Permission::query()->where('key', 'org.manage_internships')->pluck('id'));
        OrganizationMembership::query()->create([
            'organization_id' => $companyOrg->id,
            'user_id' => $companyUser->id,
            'role_id' => $role->id,
            'status' => 'active',
            'title' => 'Admin',
        ]);
        $companyUser->forceFill(['tenant_id' => $companyOrg->id, 'role_id' => $role->id])->save();
        $company = Company::query()->create([
            'user_id' => $companyUser->id,
            'organization_id' => $companyOrg->id,
            'company_name' => 'Resume Company',
            'verification_status' => 'approved',
        ]);
        Contract::query()->create([
            'school_user_id' => $schoolUser->id,
            'school_name' => $school->institution_name,
            'company_user_id' => $companyUser->id,
            'company_name' => $company->company_name,
            'requested_by_role' => 'school',
            'requested_by_user_id' => $schoolUser->id,
            'partner_user_id' => $companyUser->id,
            'status' => 'active',
            'subject' => 'Resume MOA',
        ]);

        Sanctum::actingAs($companyUser);
        $created = $this->postJson('/api/internships', [
            'title' => 'No Resume Required',
            'status' => 'active',
            'requires_resume' => true,
            'required_documents' => ['Endorsement'],
        ]);
        $created->assertCreated();
        $created->assertJsonPath('data.requiresResume', false);
        $this->assertFalse((bool) Internship::query()->find($created->json('data.id'))?->requires_resume);
    }
}
