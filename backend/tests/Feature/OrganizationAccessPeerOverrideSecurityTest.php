<?php

namespace Tests\Feature;

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

class OrganizationAccessPeerOverrideSecurityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<int, string>  $managerPermissionKeys
     * @return array{
     *   manager: User,
     *   managerMembership: OrganizationMembership,
     *   peer: User,
     *   peerMembership: OrganizationMembership,
     *   organization: Organization,
     *   managerRole: Role,
     *   peerRole: Role
     * }
     */
    private function createSchoolAccessContext(array $managerPermissionKeys = ['manage_users', 'org.manage_members']): array
    {
        foreach ([
            'manage_users' => 'Manage users',
            'org.manage_members' => 'Manage organization members',
            'org.manage_roles' => 'Manage organization roles',
            'org.manage_subscription' => 'Manage organization subscription',
            'platform.manage_users' => 'Platform manage users',
        ] as $key => $description) {
            Permission::query()->updateOrCreate(
                ['key' => $key],
                [
                    'scope' => str_starts_with($key, 'platform.') ? 'platform' : 'organization',
                    'description' => $description,
                ]
            );
        }

        $manager = User::factory()->create([
            'role' => 'school',
            'email' => 'manager.'.uniqid('', true).'@example.com',
            'is_active' => true,
        ]);

        $subscription = Subscription::query()->create([
            'plan' => 'standard',
            'status' => 'active',
            'billing_cycle' => 'monthly',
        ]);

        $organization = Organization::query()->create([
            'name' => 'Peer Override School Org',
            'type' => 'school',
            'subscription_id' => $subscription->id,
            'owner_user_id' => $manager->id,
            'settings' => [],
            'is_active' => true,
        ]);

        $managerRole = Role::query()->create([
            'tenant_id' => $organization->id,
            'name' => 'Registrar',
            'slug' => 'registrar_'.uniqid(),
            'scope' => 'organization',
            'organization_type' => 'school',
            'is_system' => false,
        ]);
        $managerRole->permissions()->sync(
            Permission::query()->whereIn('key', $managerPermissionKeys)->pluck('id')->all()
        );

        $peerRole = Role::query()->create([
            'tenant_id' => $organization->id,
            'name' => 'Teacher',
            'slug' => 'teacher_'.uniqid(),
            'scope' => 'organization',
            'organization_type' => 'school',
            'is_system' => false,
        ]);

        $managerMembership = OrganizationMembership::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $manager->id,
            'role_id' => $managerRole->id,
            'status' => 'active',
            'title' => 'Registrar',
            'permissions_override' => ['grant' => [], 'deny' => []],
        ]);

        $manager->forceFill([
            'tenant_id' => $organization->id,
            'role_id' => $managerRole->id,
        ])->save();

        School::query()->create([
            'user_id' => $manager->id,
            'organization_id' => $organization->id,
            'institution_name' => 'Peer Override School',
            'subscription_code' => 'POV-'.strtoupper(substr(uniqid(), -4)),
            'verification_status' => 'approved',
        ]);

        $peer = User::factory()->create([
            'role' => 'school',
            'email' => 'peer.'.uniqid('', true).'@example.com',
            'is_active' => true,
        ]);

        $peerMembership = OrganizationMembership::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $peer->id,
            'role_id' => $peerRole->id,
            'status' => 'active',
            'title' => 'Teacher',
            'permissions_override' => ['grant' => [], 'deny' => []],
        ]);

        $peer->forceFill([
            'tenant_id' => $organization->id,
            'role_id' => $peerRole->id,
        ])->save();

        return compact(
            'manager',
            'managerMembership',
            'peer',
            'peerMembership',
            'organization',
            'managerRole',
            'peerRole'
        );
    }

    public function test_peer_over_grant_of_unowned_permission_is_blocked(): void
    {
        $context = $this->createSchoolAccessContext(['manage_users', 'org.manage_members']);
        Sanctum::actingAs($context['manager']);

        $response = $this->patchJson(
            '/api/organization/access/members/'.$context['peerMembership']->id,
            ['grantPermissions' => ['org.manage_subscription']]
        );

        $response->assertForbidden();
        $context['peerMembership']->refresh();
        $this->assertSame([], $context['peerMembership']->permissions_override['grant'] ?? []);
        $this->assertNotContains('org.manage_subscription', $context['peerMembership']->effectivePermissions());
    }

    public function test_valid_peer_grant_of_owned_permission_is_allowed(): void
    {
        $context = $this->createSchoolAccessContext(['manage_users', 'org.manage_members']);
        Sanctum::actingAs($context['manager']);

        $response = $this->patchJson(
            '/api/organization/access/members/'.$context['peerMembership']->id,
            ['grantPermissions' => ['org.manage_members']]
        );

        $response->assertOk();
        $context['peerMembership']->refresh();
        $this->assertContains('org.manage_members', $context['peerMembership']->permissions_override['grant'] ?? []);
        $this->assertContains('org.manage_members', $context['peerMembership']->effectivePermissions());
    }

    public function test_unknown_grant_permission_is_rejected(): void
    {
        $context = $this->createSchoolAccessContext();
        Sanctum::actingAs($context['manager']);

        $response = $this->patchJson(
            '/api/organization/access/members/'.$context['peerMembership']->id,
            ['grantPermissions' => ['random.fake.permission']]
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['grantPermissions.0']);
        $context['peerMembership']->refresh();
        $this->assertSame([], $context['peerMembership']->permissions_override['grant'] ?? []);
        $this->assertNotContains('random.fake.permission', $context['peerMembership']->effectivePermissions());
    }

    public function test_platform_grant_permission_is_rejected(): void
    {
        $context = $this->createSchoolAccessContext();
        Sanctum::actingAs($context['manager']);

        $response = $this->patchJson(
            '/api/organization/access/members/'.$context['peerMembership']->id,
            ['grantPermissions' => ['platform.manage_users']]
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['grantPermissions.0']);
        $context['peerMembership']->refresh();
        $this->assertSame([], $context['peerMembership']->permissions_override['grant'] ?? []);
        $this->assertNotContains('platform.manage_users', $context['peerMembership']->effectivePermissions());
    }

    public function test_unknown_deny_permission_is_rejected(): void
    {
        $context = $this->createSchoolAccessContext();
        Sanctum::actingAs($context['manager']);

        $response = $this->patchJson(
            '/api/organization/access/members/'.$context['peerMembership']->id,
            ['denyPermissions' => ['random.fake.permission']]
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['denyPermissions.0']);
        $context['peerMembership']->refresh();
        $this->assertSame([], $context['peerMembership']->permissions_override['deny'] ?? []);
    }

    public function test_self_escalation_via_grant_permissions_still_forbidden(): void
    {
        $context = $this->createSchoolAccessContext();
        Sanctum::actingAs($context['manager']);

        $response = $this->patchJson(
            '/api/organization/access/members/'.$context['managerMembership']->id,
            ['grantPermissions' => ['org.manage_members']]
        );

        $response->assertForbidden();
        $context['managerMembership']->refresh();
        $this->assertSame([], $context['managerMembership']->permissions_override['grant'] ?? []);
    }

    public function test_cross_organization_override_update_is_forbidden(): void
    {
        $context = $this->createSchoolAccessContext();

        $otherManager = User::factory()->create([
            'role' => 'school',
            'email' => 'other-manager.'.uniqid('', true).'@example.com',
            'is_active' => true,
        ]);

        $otherSubscription = Subscription::query()->create([
            'plan' => 'standard',
            'status' => 'active',
            'billing_cycle' => 'monthly',
        ]);

        $otherOrganization = Organization::query()->create([
            'name' => 'Other School Org',
            'type' => 'school',
            'subscription_id' => $otherSubscription->id,
            'owner_user_id' => $otherManager->id,
            'settings' => [],
            'is_active' => true,
        ]);

        $otherRole = Role::query()->create([
            'tenant_id' => $otherOrganization->id,
            'name' => 'Other Teacher',
            'slug' => 'other_teacher_'.uniqid(),
            'scope' => 'organization',
            'organization_type' => 'school',
            'is_system' => false,
        ]);

        $otherPeer = User::factory()->create([
            'role' => 'school',
            'email' => 'other-peer.'.uniqid('', true).'@example.com',
            'is_active' => true,
        ]);

        $otherPeerMembership = OrganizationMembership::query()->create([
            'organization_id' => $otherOrganization->id,
            'user_id' => $otherPeer->id,
            'role_id' => $otherRole->id,
            'status' => 'active',
            'title' => 'Other Teacher',
            'permissions_override' => ['grant' => [], 'deny' => []],
        ]);

        Sanctum::actingAs($context['manager']);

        $response = $this->patchJson(
            '/api/organization/access/members/'.$otherPeerMembership->id,
            ['grantPermissions' => ['org.manage_members']]
        );

        $response->assertForbidden();
        $otherPeerMembership->refresh();
        $this->assertSame([], $otherPeerMembership->permissions_override['grant'] ?? []);
    }
}
