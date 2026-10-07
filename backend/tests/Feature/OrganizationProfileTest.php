<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\SubscriptionPlanDefinition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrganizationProfileTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{user: User, organization: Organization, role: Role}
     */
    private function createCompanyContext(bool $withManageProfile = true): array
    {
        Permission::query()->updateOrCreate(
            ['key' => 'org.manage_profile'],
            ['scope' => 'organization', 'description' => 'org.manage_profile']
        );
        Permission::query()->updateOrCreate(
            ['key' => 'org.view_reports'],
            ['scope' => 'organization', 'description' => 'org.view_reports']
        );

        SubscriptionPlanDefinition::query()->updateOrCreate(
            ['slug' => 'free'],
            [
                'name' => 'Free',
                'description' => 'Free',
                'school_price' => 0,
                'company_price' => 0,
                'school_features' => [],
                'company_features' => [],
                'school_coordinators_limit' => 1,
                'school_students_limit' => 5,
                'company_accounts_limit' => 1,
                'company_internships_limit' => 3,
                'organization_photos_limit' => 3,
                'is_active' => true,
            ]
        );

        $user = User::factory()->create([
            'role' => 'company',
            'email' => 'profile.'.uniqid('', true).'@example.com',
            'is_active' => true,
        ]);
        $sub = Subscription::query()->create([
            'plan' => 'free',
            'status' => 'active',
            'billing_cycle' => 'monthly',
        ]);
        $organization = Organization::query()->create([
            'name' => 'Profile Co',
            'type' => 'company',
            'subscription_id' => $sub->id,
            'owner_user_id' => $user->id,
            'settings' => [],
            'is_active' => true,
        ]);
        $role = Role::query()->create([
            'tenant_id' => $organization->id,
            'name' => 'Company Admin',
            'slug' => 'company_admin_'.uniqid(),
            'scope' => 'organization',
            'organization_type' => 'company',
        ]);
        $keys = $withManageProfile ? ['org.manage_profile'] : ['org.view_reports'];
        $role->permissions()->sync(Permission::query()->whereIn('key', $keys)->pluck('id'));
        OrganizationMembership::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
            'status' => 'active',
            'title' => 'Admin',
        ]);
        $user->forceFill([
            'tenant_id' => $organization->id,
            'role_id' => $role->id,
        ])->save();
        Company::query()->create([
            'user_id' => $user->id,
            'organization_id' => $organization->id,
            'company_name' => 'Profile Co',
            'verification_status' => 'approved',
        ]);

        return compact('user', 'organization', 'role');
    }

    public function test_show_returns_profile_fields_and_photo_usage(): void
    {
        $ctx = $this->createCompanyContext();
        Sanctum::actingAs($ctx['user']);

        $response = $this->getJson('/api/organization-profile');
        $response->assertOk();
        $response->assertJsonPath('data.photoLimit', 3);
        $response->assertJsonPath('data.photoCount', 0);
        $response->assertJsonPath('data.photosUsedLabel', '0 of 3 photos used');
    }

    public function test_update_strips_html_and_rejects_invalid_website(): void
    {
        $ctx = $this->createCompanyContext();
        Sanctum::actingAs($ctx['user']);

        $this->patchJson('/api/organization-profile', [
            'website' => 'javascript:alert(1)',
        ])->assertStatus(422);

        $this->patchJson('/api/organization-profile', [
            'website' => 'not-a-url',
        ])->assertStatus(422);

        $response = $this->patchJson('/api/organization-profile', [
            'tagline' => '<b>Trusted</b> host',
            'description' => '<script>alert(1)</script>Safe workplace',
            'address' => '<p>123 Main</p>',
            'city' => 'Bacoor City',
            'website' => 'https://example.com',
            'industry' => 'Technology',
            'perks' => ['<b>Free lunch</b>', 'Mentorship'],
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.tagline', 'Trusted host');
        $response->assertJsonPath('data.description', 'Safe workplace');
        $response->assertJsonPath('data.address', '123 Main');
        $response->assertJsonPath('data.website', 'https://example.com');
        $response->assertJsonPath('data.perks.0', 'Free lunch');
        $this->assertStringNotContainsString('<', (string) $response->json('data.description'));
    }

    public function test_update_requires_manage_profile_permission(): void
    {
        $ctx = $this->createCompanyContext(false);
        Sanctum::actingAs($ctx['user']);

        $this->patchJson('/api/organization-profile', [
            'tagline' => 'Hello',
        ])->assertForbidden();
    }

    public function test_cross_organization_update_is_forbidden(): void
    {
        $owner = $this->createCompanyContext();
        $other = $this->createCompanyContext();

        Sanctum::actingAs($owner['user']);
        $this->patchJson('/api/organization-profile', [
            'organization_id' => $other['organization']->id,
            'tagline' => 'Hijack attempt',
        ])->assertForbidden();

        $this->assertDatabaseMissing('organizations', [
            'id' => $other['organization']->id,
            'tagline' => 'Hijack attempt',
        ]);
        $this->assertDatabaseMissing('organizations', [
            'id' => $owner['organization']->id,
            'tagline' => 'Hijack attempt',
        ]);
    }
}
