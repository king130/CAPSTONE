<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\School;
use App\Models\OrganizationMembership;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use App\Services\SubscriptionPlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlanSeatEnforcementTest extends TestCase
{
    use RefreshDatabase;

    public function test_free_plan_pauses_extra_school_coordinators_and_paid_plan_restores_them(): void
    {
        [$owner, $organization, $subscription] = $this->makeSchool('free');
        $extras = [];
        foreach (range(1, 4) as $index) {
            $extras[] = $this->addCoordinator($organization, 'Coordinator '.$index);
        }

        app(SubscriptionPlanService::class)->syncOrganizationCompliance($organization);

        $this->assertTrue($owner->fresh()->is_active);
        $this->assertSame('active', OrganizationMembership::query()->where('user_id', $owner->id)->value('status'));

        foreach ($extras as $extra) {
            $membership = OrganizationMembership::query()->where('user_id', $extra->id)->first();
            $this->assertFalse($extra->fresh()->is_active);
            $this->assertSame('inactive', $membership->status);
            $this->assertTrue((bool) ($membership->permissions_override['planLimitHold'] ?? false));
        }

        $holds = $organization->fresh()->settings['plan_seat_holds'] ?? [];
        $this->assertSame(4, $holds[0]['paused'] ?? null);
        $this->assertSame(1, $holds[0]['limit'] ?? null);

        $subscription->update(['plan' => 'standard', 'status' => 'active']);
        app(SubscriptionPlanService::class)->syncOrganizationCompliance($organization->fresh());

        foreach ($extras as $extra) {
            $membership = OrganizationMembership::query()->where('user_id', $extra->id)->first();
            $this->assertTrue($extra->fresh()->is_active);
            $this->assertSame('active', $membership->status);
            $this->assertArrayNotHasKey('planLimitHold', $membership->permissions_override ?? []);
        }

        $this->assertArrayNotHasKey('plan_seat_holds', $organization->fresh()->settings ?? []);
    }

    public function test_owner_login_pauses_extra_coordinators_and_blocks_a_new_one(): void
    {
        [$owner, $organization] = $this->makeSchool('free');
        $extra = $this->addCoordinator($organization, 'Extra Coordinator');

        $permission = Permission::query()->updateOrCreate(
            ['key' => 'org.manage_members'],
            ['scope' => 'organization', 'description' => 'Manage members']
        );
        $role = Role::query()->where('tenant_id', $organization->id)->firstOrFail();
        $role->permissions()->sync([$permission->id]);

        Sanctum::actingAs($owner);
        $this->getJson('/api/auth/me')->assertOk();

        $this->assertFalse($extra->fresh()->is_active);

        $this->postJson('/api/rbac/tenants/'.$organization->id.'/members', [
            'name' => 'Another Coordinator',
            'email' => 'another-coordinator@example.com',
            'roleId' => $role->id,
        ])->assertUnprocessable();
    }

    public function test_manually_disabled_coordinator_stays_disabled_after_upgrade(): void
    {
        [$owner, $organization, $subscription] = $this->makeSchool('free');
        $manual = $this->addCoordinator($organization, 'Manual Off');
        $membership = OrganizationMembership::query()->where('user_id', $manual->id)->first();
        $membership->update(['status' => 'inactive']);
        $manual->forceFill(['is_active' => false])->save();

        $subscription->update(['plan' => 'standard', 'status' => 'active']);
        app(SubscriptionPlanService::class)->syncOrganizationCompliance($organization->fresh());

        $this->assertFalse($manual->fresh()->is_active);
        $this->assertSame('inactive', $membership->fresh()->status);
    }

    /**
     * @return array{0: User, 1: Organization, 2: Subscription}
     */
    private function makeSchool(string $plan): array
    {
        $owner = User::factory()->create([
            'role' => 'school',
            'is_active' => true,
            'password' => 'School@2026!',
        ]);

        $subscription = Subscription::query()->create([
            'plan' => $plan,
            'status' => 'active',
            'billing_cycle' => 'monthly',
        ]);

        $organization = Organization::query()->create([
            'name' => 'Seat School',
            'type' => 'school',
            'subscription_id' => $subscription->id,
            'owner_user_id' => $owner->id,
            'settings' => [],
            'is_active' => true,
        ]);

        $role = Role::query()->create([
            'tenant_id' => $organization->id,
            'name' => 'School Admin',
            'slug' => 'school_admin_'.uniqid(),
            'scope' => 'organization',
            'organization_type' => 'school',
            'is_system' => false,
        ]);

        OrganizationMembership::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $owner->id,
            'role_id' => $role->id,
            'status' => 'active',
            'title' => 'Owner',
        ]);

        $owner->forceFill([
            'tenant_id' => $organization->id,
            'role_id' => $role->id,
        ])->save();

        School::query()->create([
            'user_id' => $owner->id,
            'subscription_id' => $subscription->id,
            'organization_id' => $organization->id,
            'institution_name' => $organization->name,
            'verification_status' => 'approved',
        ]);

        return [$owner, $organization, $subscription];
    }

    private function addCoordinator(Organization $organization, string $name): User
    {
        $role = Role::query()->where('tenant_id', $organization->id)->firstOrFail();
        $user = User::factory()->create([
            'role' => 'school',
            'name' => $name,
            'is_active' => true,
            'tenant_id' => $organization->id,
            'role_id' => $role->id,
        ]);

        OrganizationMembership::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
            'status' => 'active',
            'title' => 'Coordinator',
        ]);

        return $user;
    }
}
