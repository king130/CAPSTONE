<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Internship;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InternshipAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{user: User, organization: Organization, company: Company, internship: Internship, role: Role}
     */
    private function createCompanyInternshipContext(string $withPermissionKey = 'org.manage_internships'): array
    {
        Permission::query()->updateOrCreate(
            ['key' => 'org.manage_internships'],
            ['scope' => 'organization', 'description' => 'Create and update internships']
        );
        Permission::query()->updateOrCreate(
            ['key' => 'manage_internships'],
            ['scope' => 'organization', 'description' => 'Create and manage internships']
        );

        $user = User::factory()->create([
            'role' => 'company',
            'email' => 'internship.'.uniqid('', true).'@example.com',
        ]);

        $subscription = Subscription::query()->create([
            'plan' => 'standard',
            'status' => 'active',
            'billing_cycle' => 'monthly',
        ]);

        $organization = Organization::query()->create([
            'name' => 'Internship Test Org',
            'type' => 'company',
            'subscription_id' => $subscription->id,
            'owner_user_id' => $user->id,
            'settings' => [],
            'is_active' => true,
        ]);

        $role = Role::query()->create([
            'tenant_id' => $organization->id,
            'name' => 'Internship Role',
            'slug' => 'internship_role_'.uniqid(),
            'scope' => 'organization',
            'organization_type' => 'company',
            'is_system' => false,
        ]);

        if ($withPermissionKey !== '') {
            $permission = Permission::query()->where('key', $withPermissionKey)->firstOrFail();
            $role->permissions()->sync([$permission->id]);
        }

        OrganizationMembership::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
            'status' => 'active',
            'title' => 'Member',
        ]);

        $user->forceFill([
            'tenant_id' => $organization->id,
            'role_id' => $role->id,
        ])->save();

        $company = Company::query()->create([
            'user_id' => $user->id,
            'organization_id' => $organization->id,
            'company_name' => 'Internship Test Company',
            'verification_status' => 'approved',
        ]);

        $internship = Internship::query()->create([
            'company_id' => $company->id,
            'host_type' => 'company',
            'host_name' => $company->company_name,
            'title' => 'Original Internship Title',
            'description' => 'Original description',
            'status' => 'active',
            'slots_available' => 1,
            'approval_status' => 'approved',
        ]);

        return compact('user', 'organization', 'company', 'internship', 'role');
    }

    public function test_unauthorized_member_cannot_update_internship(): void
    {
        $context = $this->createCompanyInternshipContext('');
        Sanctum::actingAs($context['user']);

        $response = $this->patchJson('/api/internships/'.$context['internship']->id, [
            'title' => 'Hacked Title',
        ]);

        $response->assertForbidden();
        $context['internship']->refresh();
        $this->assertSame('Original Internship Title', $context['internship']->title);
    }

    public function test_unauthorized_member_cannot_delete_internship(): void
    {
        $context = $this->createCompanyInternshipContext('');
        Sanctum::actingAs($context['user']);

        $response = $this->deleteJson('/api/internships/'.$context['internship']->id);

        $response->assertForbidden();
        $this->assertDatabaseHas('internships', [
            'id' => $context['internship']->id,
            'title' => 'Original Internship Title',
        ]);
    }

    public function test_authorized_member_can_update_internship(): void
    {
        $context = $this->createCompanyInternshipContext();
        Sanctum::actingAs($context['user']);

        $response = $this->patchJson('/api/internships/'.$context['internship']->id, [
            'title' => 'Updated Internship Title',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.title', 'Updated Internship Title');
        $context['internship']->refresh();
        $this->assertSame('Updated Internship Title', $context['internship']->title);
    }

    public function test_authorized_member_can_delete_internship(): void
    {
        $context = $this->createCompanyInternshipContext();
        Sanctum::actingAs($context['user']);

        $response = $this->deleteJson('/api/internships/'.$context['internship']->id);

        $response->assertNoContent();
        $this->assertDatabaseMissing('internships', [
            'id' => $context['internship']->id,
        ]);
    }

    public function test_cross_organization_member_cannot_update_or_delete_internship(): void
    {
        $orgA = $this->createCompanyInternshipContext();
        $orgB = $this->createCompanyInternshipContext();

        Sanctum::actingAs($orgA['user']);

        $updateResponse = $this->patchJson('/api/internships/'.$orgB['internship']->id, [
            'title' => 'Cross Org Title',
        ]);
        $updateResponse->assertForbidden();

        $deleteResponse = $this->deleteJson('/api/internships/'.$orgB['internship']->id);
        $deleteResponse->assertForbidden();

        $orgB['internship']->refresh();
        $this->assertSame('Original Internship Title', $orgB['internship']->title);
        $this->assertDatabaseHas('internships', [
            'id' => $orgB['internship']->id,
        ]);
    }
}
