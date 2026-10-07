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

class TenantRbacSecurityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<int, string>  $managerPermissionKeys
     * @return array{
     *   owner: User,
     *   ownerMembership: OrganizationMembership,
     *   manager: User,
     *   managerMembership: OrganizationMembership,
     *   peer: User,
     *   peerMembership: OrganizationMembership,
     *   organization: Organization,
     *   ownerRole: Role,
     *   managerRole: Role,
     *   peerRole: Role,
     *   customRole: Role
     * }
     */
    private function createSchoolTenantContext(array $managerPermissionKeys = [
        'org.manage_roles',
        'manage_roles',
        'org.manage_members',
    ]): array
    {
        foreach ([
            'org.manage_roles' => 'Manage organization roles',
            'manage_roles' => 'Manage roles',
            'manage_permissions' => 'Manage permissions',
            'org.manage_members' => 'Manage organization members',
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

        $owner = User::factory()->create([
            'role' => 'school',
            'email' => 'owner.'.uniqid('', true).'@example.com',
            'is_active' => true,
        ]);

        $subscription = Subscription::query()->create([
            'plan' => 'standard',
            'status' => 'active',
            'billing_cycle' => 'monthly',
        ]);

        $organization = Organization::query()->create([
            'name' => 'Tenant RBAC School Org',
            'type' => 'school',
            'subscription_id' => $subscription->id,
            'owner_user_id' => $owner->id,
            'settings' => [],
            'is_active' => true,
        ]);

        $ownerRole = Role::query()->create([
            'tenant_id' => $organization->id,
            'name' => 'School Admin',
            'slug' => 'school_admin_'.uniqid(),
            'scope' => 'organization',
            'organization_type' => 'school',
            'is_system' => true,
        ]);
        $ownerRole->permissions()->sync(
            Permission::query()->whereIn('key', [
                'org.manage_roles',
                'manage_roles',
                'org.manage_members',
                'org.manage_subscription',
            ])->pluck('id')->all()
        );

        $managerRole = Role::query()->create([
            'tenant_id' => $organization->id,
            'name' => 'Role Manager',
            'slug' => 'role_manager_'.uniqid(),
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

        $customRole = Role::query()->create([
            'tenant_id' => $organization->id,
            'name' => 'Custom Role',
            'slug' => 'custom_'.uniqid(),
            'scope' => 'organization',
            'organization_type' => 'school',
            'is_system' => false,
        ]);

        $ownerMembership = OrganizationMembership::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $owner->id,
            'role_id' => $ownerRole->id,
            'status' => 'active',
            'title' => 'Primary Admin',
            'permissions_override' => ['grant' => [], 'deny' => []],
        ]);

        $owner->forceFill([
            'tenant_id' => $organization->id,
            'role_id' => $ownerRole->id,
        ])->save();

        School::query()->create([
            'user_id' => $owner->id,
            'organization_id' => $organization->id,
            'institution_name' => 'Tenant RBAC School',
            'subscription_code' => 'TRB-'.strtoupper(substr(uniqid(), -4)),
            'verification_status' => 'approved',
        ]);

        $manager = User::factory()->create([
            'role' => 'school',
            'email' => 'manager.'.uniqid('', true).'@example.com',
            'is_active' => true,
        ]);

        $managerMembership = OrganizationMembership::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $manager->id,
            'role_id' => $managerRole->id,
            'status' => 'active',
            'title' => 'Role Manager',
            'permissions_override' => ['grant' => [], 'deny' => []],
        ]);

        $manager->forceFill([
            'tenant_id' => $organization->id,
            'role_id' => $managerRole->id,
        ])->save();

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
            'owner',
            'ownerMembership',
            'manager',
            'managerMembership',
            'peer',
            'peerMembership',
            'organization',
            'ownerRole',
            'managerRole',
            'peerRole',
            'customRole'
        );
    }

    public function test_tenant_role_over_grant_is_blocked(): void
    {
        $context = $this->createSchoolTenantContext([
            'org.manage_roles',
            'manage_roles',
            'org.manage_members',
        ]);
        Sanctum::actingAs($context['manager']);

        $response = $this->putJson(
            '/api/rbac/tenants/'.$context['organization']->id.'/roles/'.$context['customRole']->id.'/permissions',
            ['permissions' => ['org.manage_roles', 'org.manage_subscription']]
        );

        $response->assertForbidden();
        $context['customRole']->load('permissions');
        $this->assertNotContains('org.manage_subscription', $context['customRole']->permissions->pluck('key')->all());
    }

    public function test_valid_tenant_permission_sync_is_allowed(): void
    {
        $context = $this->createSchoolTenantContext([
            'org.manage_roles',
            'manage_roles',
            'org.manage_members',
        ]);
        Sanctum::actingAs($context['manager']);

        $response = $this->putJson(
            '/api/rbac/tenants/'.$context['organization']->id.'/roles/'.$context['customRole']->id.'/permissions',
            ['permissions' => ['org.manage_roles', 'org.manage_members']]
        );

        $response->assertOk();
        $context['customRole']->load('permissions');
        $keys = $context['customRole']->permissions->pluck('key')->all();
        $this->assertContains('org.manage_roles', $keys);
        $this->assertContains('org.manage_members', $keys);
    }

    public function test_unknown_permission_sync_is_rejected(): void
    {
        $context = $this->createSchoolTenantContext();
        Sanctum::actingAs($context['manager']);

        $response = $this->putJson(
            '/api/rbac/tenants/'.$context['organization']->id.'/roles/'.$context['customRole']->id.'/permissions',
            ['permissions' => ['random.fake.permission']]
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['permissions.0']);
        $context['customRole']->load('permissions');
        $this->assertNotContains('random.fake.permission', $context['customRole']->permissions->pluck('key')->all());
    }

    public function test_platform_permission_sync_is_rejected(): void
    {
        $context = $this->createSchoolTenantContext();
        Sanctum::actingAs($context['manager']);

        $response = $this->putJson(
            '/api/rbac/tenants/'.$context['organization']->id.'/roles/'.$context['customRole']->id.'/permissions',
            ['permissions' => ['platform.manage_users']]
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['permissions.0']);
        $context['customRole']->load('permissions');
        $this->assertNotContains('platform.manage_users', $context['customRole']->permissions->pluck('key')->all());
    }

    public function test_tenant_member_self_escalation_is_blocked(): void
    {
        $context = $this->createSchoolTenantContext();
        Sanctum::actingAs($context['manager']);

        $response = $this->patchJson(
            '/api/rbac/tenants/'.$context['organization']->id.'/members/'.$context['managerMembership']->id,
            ['roleId' => $context['ownerRole']->id]
        );

        $response->assertForbidden();
        $context['managerMembership']->refresh();
        $this->assertSame($context['managerRole']->id, $context['managerMembership']->role_id);
    }

    public function test_tenant_owner_role_modification_is_blocked(): void
    {
        $context = $this->createSchoolTenantContext();
        Sanctum::actingAs($context['manager']);

        $response = $this->patchJson(
            '/api/rbac/tenants/'.$context['organization']->id.'/members/'.$context['ownerMembership']->id,
            ['roleId' => $context['peerRole']->id]
        );

        $response->assertForbidden();
        $context['ownerMembership']->refresh();
        $this->assertSame($context['ownerRole']->id, $context['ownerMembership']->role_id);
    }

    public function test_tenant_owner_status_modification_is_blocked(): void
    {
        $context = $this->createSchoolTenantContext();
        Sanctum::actingAs($context['manager']);

        $response = $this->patchJson(
            '/api/rbac/tenants/'.$context['organization']->id.'/members/'.$context['ownerMembership']->id,
            [
                'roleId' => $context['ownerRole']->id,
                'status' => 'inactive',
            ]
        );

        $response->assertForbidden();
        $context['ownerMembership']->refresh();
        $this->assertSame('active', $context['ownerMembership']->status);
        $this->assertSame($context['ownerRole']->id, $context['ownerMembership']->role_id);
    }

    public function test_cross_organization_tenant_membership_update_is_forbidden(): void
    {
        $context = $this->createSchoolTenantContext();

        $otherOwner = User::factory()->create([
            'role' => 'school',
            'email' => 'other-owner.'.uniqid('', true).'@example.com',
            'is_active' => true,
        ]);

        $otherSubscription = Subscription::query()->create([
            'plan' => 'standard',
            'status' => 'active',
            'billing_cycle' => 'monthly',
        ]);

        $otherOrganization = Organization::query()->create([
            'name' => 'Other Tenant Org',
            'type' => 'school',
            'subscription_id' => $otherSubscription->id,
            'owner_user_id' => $otherOwner->id,
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
        ]);

        Sanctum::actingAs($context['manager']);

        $response = $this->patchJson(
            '/api/rbac/tenants/'.$otherOrganization->id.'/members/'.$otherPeerMembership->id,
            ['roleId' => $otherRole->id]
        );

        $response->assertForbidden();
        $otherPeerMembership->refresh();
        $this->assertSame($otherRole->id, $otherPeerMembership->role_id);
    }

    public function test_manager_can_still_update_non_owner_peer_role(): void
    {
        $context = $this->createSchoolTenantContext();
        Sanctum::actingAs($context['manager']);

        $response = $this->patchJson(
            '/api/rbac/tenants/'.$context['organization']->id.'/members/'.$context['peerMembership']->id,
            ['roleId' => $context['managerRole']->id]
        );

        $response->assertOk();
        $context['peerMembership']->refresh();
        $this->assertSame($context['managerRole']->id, $context['peerMembership']->role_id);
    }
}
