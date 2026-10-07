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

class OrganizationAccessSelfEscalationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{
     *   manager: User,
     *   managerMembership: OrganizationMembership,
     *   peer: User,
     *   peerMembership: OrganizationMembership,
     *   organization: Organization,
     *   managerRole: Role,
     *   adminRole: Role,
     *   peerRole: Role
     * }
     */
    private function createSchoolAccessContext(): array
    {
        foreach ([
            'manage_users' => 'Manage users',
            'org.manage_members' => 'Manage organization members',
            'org.manage_roles' => 'Manage organization roles',
        ] as $key => $description) {
            Permission::query()->updateOrCreate(
                ['key' => $key],
                ['scope' => 'organization', 'description' => $description]
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
            'name' => 'RBAC School Org',
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
            Permission::query()->whereIn('key', ['manage_users', 'org.manage_members'])->pluck('id')->all()
        );

        $adminRole = Role::query()->create([
            'tenant_id' => $organization->id,
            'name' => 'School Admin',
            'slug' => 'school_admin',
            'scope' => 'organization',
            'organization_type' => 'school',
            'is_system' => true,
        ]);
        $adminRole->permissions()->sync(
            Permission::query()->whereIn('key', ['manage_users', 'org.manage_members', 'org.manage_roles'])->pluck('id')->all()
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
            'institution_name' => 'RBAC School',
            'subscription_code' => 'RBAC-'.strtoupper(substr(uniqid(), -4)),
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
            'adminRole',
            'peerRole'
        );
    }

    public function test_member_cannot_change_own_role(): void
    {
        $context = $this->createSchoolAccessContext();
        Sanctum::actingAs($context['manager']);

        $response = $this->patchJson(
            '/api/organization/access/members/'.$context['managerMembership']->id,
            ['roleId' => $context['adminRole']->id]
        );

        $response->assertForbidden();
        $context['managerMembership']->refresh();
        $this->assertSame($context['managerRole']->id, $context['managerMembership']->role_id);
        $this->assertNotSame($context['adminRole']->id, $context['managerMembership']->role_id);
    }

    public function test_member_cannot_grant_own_permissions(): void
    {
        $context = $this->createSchoolAccessContext();
        Sanctum::actingAs($context['manager']);

        $response = $this->patchJson(
            '/api/organization/access/members/'.$context['managerMembership']->id,
            ['grantPermissions' => ['org.manage_roles']]
        );

        $response->assertForbidden();
        $context['managerMembership']->refresh();
        $this->assertSame([], $context['managerMembership']->permissions_override['grant'] ?? []);
        $this->assertNotContains('org.manage_roles', $context['managerMembership']->effectivePermissions());
    }

    public function test_member_cannot_promote_own_membership_to_admin(): void
    {
        $context = $this->createSchoolAccessContext();
        Sanctum::actingAs($context['manager']);

        $response = $this->patchJson(
            '/api/organization/access/members/'.$context['managerMembership']->id,
            [
                'roleId' => $context['adminRole']->id,
                'grantPermissions' => ['org.manage_roles'],
            ]
        );

        $response->assertForbidden();
        $context['managerMembership']->refresh();
        $this->assertSame($context['managerRole']->id, $context['managerMembership']->role_id);
        $this->assertNotSame($context['adminRole']->id, $context['managerMembership']->role_id);
        $this->assertSame([], $context['managerMembership']->permissions_override['grant'] ?? []);
    }

    public function test_member_cannot_change_own_membership_status(): void
    {
        $context = $this->createSchoolAccessContext();
        Sanctum::actingAs($context['manager']);

        $response = $this->patchJson(
            '/api/organization/access/members/'.$context['managerMembership']->id,
            ['status' => 'inactive']
        );

        $response->assertForbidden();
        $context['managerMembership']->refresh();
        $this->assertSame('active', $context['managerMembership']->status);
    }

    public function test_authorized_manager_can_still_update_another_member(): void
    {
        $context = $this->createSchoolAccessContext();
        Sanctum::actingAs($context['manager']);

        $response = $this->patchJson(
            '/api/organization/access/members/'.$context['peerMembership']->id,
            [
                'roleId' => $context['adminRole']->id,
                'title' => 'Promoted Peer',
            ]
        );

        $response->assertOk();
        $context['peerMembership']->refresh();
        $this->assertSame($context['adminRole']->id, $context['peerMembership']->role_id);
        $this->assertSame('Promoted Peer', $context['peerMembership']->title);
    }
}
