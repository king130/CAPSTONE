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

class OrganizationAccessOwnerProtectionTest extends TestCase
{
    use RefreshDatabase;

    /**
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
     *   peerRole: Role
     * }
     */
    private function createSchoolOwnerContext(): array
    {
        foreach ([
            'manage_users' => 'Manage users',
            'org.manage_members' => 'Manage organization members',
            'org.manage_roles' => 'Manage organization roles',
            'org.manage_subscription' => 'Manage organization subscription',
        ] as $key => $description) {
            Permission::query()->updateOrCreate(
                ['key' => $key],
                ['scope' => 'organization', 'description' => $description]
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
            'name' => 'Owner Protect School Org',
            'type' => 'school',
            'subscription_id' => $subscription->id,
            'owner_user_id' => $owner->id,
            'settings' => [],
            'is_active' => true,
        ]);

        $ownerRole = Role::query()->create([
            'tenant_id' => $organization->id,
            'name' => 'School Admin',
            'slug' => 'school_admin',
            'scope' => 'organization',
            'organization_type' => 'school',
            'is_system' => true,
        ]);
        $ownerRole->permissions()->sync(
            Permission::query()->whereIn('key', [
                'manage_users',
                'org.manage_members',
                'org.manage_roles',
                'org.manage_subscription',
            ])->pluck('id')->all()
        );

        $managerRole = Role::query()->create([
            'tenant_id' => $organization->id,
            'name' => 'Registrar',
            'slug' => 'registrar_'.uniqid(),
            'scope' => 'organization',
            'organization_type' => 'school',
            'is_system' => false,
        ]);
        $managerRole->permissions()->sync(
            Permission::query()->whereIn('key', [
                'manage_users',
                'org.manage_members',
                'org.manage_roles',
                'org.manage_subscription',
            ])->pluck('id')->all()
        );

        $peerRole = Role::query()->create([
            'tenant_id' => $organization->id,
            'name' => 'Teacher',
            'slug' => 'teacher_'.uniqid(),
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
            'institution_name' => 'Owner Protect School',
            'subscription_code' => 'OWN-'.strtoupper(substr(uniqid(), -4)),
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
            'title' => 'Registrar',
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
            'peerRole'
        );
    }

    public function test_manager_cannot_demote_owner_role(): void
    {
        $context = $this->createSchoolOwnerContext();
        Sanctum::actingAs($context['manager']);

        $response = $this->patchJson(
            '/api/organization/access/members/'.$context['ownerMembership']->id,
            ['roleId' => $context['peerRole']->id]
        );

        $response->assertForbidden();
        $context['ownerMembership']->refresh();
        $this->assertSame($context['ownerRole']->id, $context['ownerMembership']->role_id);
        $this->assertNotSame($context['peerRole']->id, $context['ownerMembership']->role_id);
    }

    public function test_manager_cannot_deactivate_owner_membership(): void
    {
        $context = $this->createSchoolOwnerContext();
        Sanctum::actingAs($context['manager']);

        $response = $this->patchJson(
            '/api/organization/access/members/'.$context['ownerMembership']->id,
            ['status' => 'inactive']
        );

        $response->assertForbidden();
        $context['ownerMembership']->refresh();
        $this->assertSame('active', $context['ownerMembership']->status);
    }

    public function test_manager_cannot_deny_permissions_on_owner(): void
    {
        $context = $this->createSchoolOwnerContext();
        Sanctum::actingAs($context['manager']);

        $response = $this->patchJson(
            '/api/organization/access/members/'.$context['ownerMembership']->id,
            ['denyPermissions' => ['org.manage_roles']]
        );

        $response->assertForbidden();
        $context['ownerMembership']->refresh();
        $this->assertSame([], $context['ownerMembership']->permissions_override['deny'] ?? []);
    }

    public function test_manager_cannot_grant_permissions_on_owner(): void
    {
        $context = $this->createSchoolOwnerContext();
        Sanctum::actingAs($context['manager']);

        $response = $this->patchJson(
            '/api/organization/access/members/'.$context['ownerMembership']->id,
            ['grantPermissions' => ['org.manage_subscription']]
        );

        $response->assertForbidden();
        $context['ownerMembership']->refresh();
        $this->assertSame([], $context['ownerMembership']->permissions_override['grant'] ?? []);
    }

    public function test_manager_can_still_update_non_owner_member(): void
    {
        $context = $this->createSchoolOwnerContext();
        Sanctum::actingAs($context['manager']);

        $response = $this->patchJson(
            '/api/organization/access/members/'.$context['peerMembership']->id,
            [
                'roleId' => $context['managerRole']->id,
                'title' => 'Promoted Peer',
                'grantPermissions' => ['org.manage_members'],
            ]
        );

        $response->assertOk();
        $context['peerMembership']->refresh();
        $this->assertSame($context['managerRole']->id, $context['peerMembership']->role_id);
        $this->assertSame('Promoted Peer', $context['peerMembership']->title);
        $this->assertContains('org.manage_members', $context['peerMembership']->permissions_override['grant'] ?? []);
    }

    public function test_self_escalation_still_blocked_for_manager(): void
    {
        $context = $this->createSchoolOwnerContext();
        Sanctum::actingAs($context['manager']);

        $response = $this->patchJson(
            '/api/organization/access/members/'.$context['managerMembership']->id,
            ['grantPermissions' => ['org.manage_roles']]
        );

        $response->assertForbidden();
        $context['managerMembership']->refresh();
        $this->assertSame([], $context['managerMembership']->permissions_override['grant'] ?? []);
    }

    public function test_cross_organization_owner_membership_update_is_forbidden(): void
    {
        $context = $this->createSchoolOwnerContext();

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
            'name' => 'Other Owner School Org',
            'type' => 'school',
            'subscription_id' => $otherSubscription->id,
            'owner_user_id' => $otherOwner->id,
            'settings' => [],
            'is_active' => true,
        ]);

        $otherOwnerRole = Role::query()->create([
            'tenant_id' => $otherOrganization->id,
            'name' => 'School Admin',
            'slug' => 'school_admin_'.uniqid(),
            'scope' => 'organization',
            'organization_type' => 'school',
            'is_system' => true,
        ]);

        $otherOwnerMembership = OrganizationMembership::query()->create([
            'organization_id' => $otherOrganization->id,
            'user_id' => $otherOwner->id,
            'role_id' => $otherOwnerRole->id,
            'status' => 'active',
            'title' => 'Other Primary Admin',
            'permissions_override' => ['grant' => [], 'deny' => []],
        ]);

        Sanctum::actingAs($context['manager']);

        $response = $this->patchJson(
            '/api/organization/access/members/'.$otherOwnerMembership->id,
            ['status' => 'inactive']
        );

        $response->assertForbidden();
        $otherOwnerMembership->refresh();
        $this->assertSame('active', $otherOwnerMembership->status);
        $this->assertSame($otherOwnerRole->id, $otherOwnerMembership->role_id);
    }
}
