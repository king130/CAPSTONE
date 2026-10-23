<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Permission;
use App\Models\Role;
use App\Models\School;
use App\Models\Student;
use App\Models\Subscription;
use App\Models\SubscriptionAuditLog;
use App\Models\SubscriptionPlanDefinition;
use App\Models\User;
use App\Services\SubscriptionAccessService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SubscriptionTrialAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'subscription.trial_days' => 14,
            'subscription.grace_days' => 7,
            'subscription.trial_plan' => 'standard',
            'subscription.timezone' => 'Asia/Manila',
        ]);

        // Ensure catalog flags exist even if an older sqlite fixture skipped the Phase H migration path.
        SubscriptionPlanDefinition::query()->where('slug', 'free')->update([
            'is_selectable' => false,
            'is_legacy' => true,
        ]);
        SubscriptionPlanDefinition::query()->whereIn('slug', ['standard', 'premium'])->update([
            'is_selectable' => true,
            'is_legacy' => false,
        ]);
    }

    /**
     * @return array{user: User, organization: Organization, subscription: Subscription}
     */
    private function createOrg(string $type = 'company'): array
    {
        Permission::query()->updateOrCreate(
            ['key' => 'org.manage_internships'],
            ['scope' => 'organization', 'description' => 'Manage internships']
        );
        Permission::query()->updateOrCreate(
            ['key' => 'org.manage_students'],
            ['scope' => 'organization', 'description' => 'Manage students']
        );
        Permission::query()->updateOrCreate(
            ['key' => 'org.manage_applications'],
            ['scope' => 'organization', 'description' => 'Manage applications']
        );
        Permission::query()->updateOrCreate(
            ['key' => 'org.manage_subscription'],
            ['scope' => 'organization', 'description' => 'Manage subscription']
        );

        $user = User::factory()->create([
            'role' => $type === 'municipal_hall' ? 'municipal_hall' : $type,
            'email' => $type.'.'.uniqid('', true).'@example.com',
        ]);

        $subscription = Subscription::query()->create([
            'plan' => 'standard',
            'status' => 'trialing',
            'billing_cycle' => 'monthly',
        ]);

        $organization = Organization::query()->create([
            'name' => ucfirst($type).' Org '.uniqid(),
            'type' => $type,
            'subscription_id' => $subscription->id,
            'owner_user_id' => $user->id,
            'settings' => [],
            'is_active' => true,
        ]);

        app(SubscriptionAccessService::class)->startTrial($subscription, $organization, $user);

        $role = Role::query()->create([
            'tenant_id' => $organization->id,
            'name' => 'Admin',
            'slug' => 'org_admin_'.uniqid(),
            'scope' => 'organization',
            'organization_type' => $type === 'municipal_hall' ? 'company' : $type,
            'is_system' => false,
        ]);
        $role->permissions()->sync(Permission::query()->pluck('id'));

        OrganizationMembership::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
            'status' => 'active',
            'title' => 'Admin',
        ]);

        if ($type === 'school') {
            School::query()->create([
                'user_id' => $user->id,
                'organization_id' => $organization->id,
                'subscription_id' => $subscription->id,
                'institution_name' => $organization->name,
                'verification_status' => 'approved',
            ]);
        } else {
            Company::query()->create([
                'user_id' => $user->id,
                'organization_id' => $organization->id,
                'subscription_id' => $subscription->id,
                'company_name' => $organization->name,
                'company_type' => $type === 'municipal_hall' ? 'municipal_hall' : null,
                'verification_status' => 'approved',
            ]);
        }

        return compact('user', 'organization', 'subscription');
    }

    public function test_new_org_of_each_type_starts_trial(): void
    {
        foreach (['school', 'company', 'municipal_hall'] as $type) {
            $context = $this->createOrg($type);
            $access = app(SubscriptionAccessService::class)->resolve($context['organization']->fresh('subscription'));
            $this->assertSame('trial', $access['status'], $type);
            $this->assertTrue($access['canWrite'], $type);
            $this->assertSame('standard', $access['plan'], $type);
            $this->assertNotNull($context['organization']->subscription->trial_started_at);
            $this->assertNotNull($context['organization']->subscription->trial_ends_at);
        }
    }

    public function test_free_plan_is_not_selectable(): void
    {
        $free = SubscriptionPlanDefinition::query()->where('slug', 'free')->first();
        $this->assertNotNull($free);
        $this->assertFalse((bool) $free->is_selectable);
        $this->assertTrue((bool) $free->is_legacy);

        $response = $this->getJson('/api/subscription-plans');
        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertNotContains('free', $ids);
        $this->assertContains('standard', $ids);
    }

    public function test_trial_days_use_asia_manila(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-18 10:00:00', 'Asia/Manila'));
        $context = $this->createOrg('company');
        $ends = $context['organization']->fresh('subscription')->subscription->trial_ends_at;
        $this->assertSame(
            Carbon::parse('2026-11-01 10:00:00', 'Asia/Manila')->toDateString(),
            Carbon::parse($ends)->timezone('Asia/Manila')->toDateString()
        );
        Carbon::setTestNow();
    }

    public function test_status_transitions_trial_grace_expired(): void
    {
        $access = app(SubscriptionAccessService::class);
        $context = $this->createOrg('school');
        $subscription = $context['organization']->subscription;

        Carbon::setTestNow(Carbon::parse($subscription->trial_ends_at)->timezone('Asia/Manila')->subDay());
        $this->assertSame('trial', $access->resolve($context['organization']->fresh('subscription'))['status']);

        Carbon::setTestNow(Carbon::parse($subscription->trial_ends_at)->timezone('Asia/Manila')->addDay());
        $this->assertSame('grace', $access->resolve($context['organization']->fresh('subscription'))['status']);

        Carbon::setTestNow(Carbon::parse($subscription->grace_ends_at)->timezone('Asia/Manila')->addDay());
        $state = $access->resolve($context['organization']->fresh('subscription'));
        $this->assertSame('expired', $state['status']);
        $this->assertFalse($state['canWrite']);
        Carbon::setTestNow();
    }

    public function test_expired_org_blocked_on_writes_reads_allowed(): void
    {
        foreach (['school', 'company', 'municipal_hall'] as $type) {
            $context = $this->createOrg($type);
            app(SubscriptionAccessService::class)->forceExpired(
                $context['organization']->subscription,
                $context['organization'],
                $context['user'],
                'test expire'
            );

            Sanctum::actingAs($context['user']);

            if ($type === 'school') {
                $blocked = $this->postJson('/api/school-students', [
                    'fullName' => 'Test Student',
                    'email' => 'student.'.uniqid().'@example.com',
                    'course' => 'BSIT',
                    'yearLevel' => '4th Year',
                ]);
            } else {
                $blocked = $this->postJson('/api/internships', [
                    'title' => 'Blocked Posting',
                    'description' => 'Should not create',
                    'slots' => 1,
                ]);
            }

            $blocked->assertStatus(402);
            $blocked->assertJsonPath('code', 'subscription_expired');

            // Reads remain allowed (auth/me and public internship catalog).
            $this->getJson('/api/auth/me')->assertOk()
                ->assertJsonPath('subscriptionAccess.status', 'expired')
                ->assertJsonPath('subscriptionAccess.canWrite', false);
            $this->getJson('/api/internships')->assertOk();
        }
    }

    private function makePlatformAdmin(): User
    {
        $platformRole = Role::query()->create([
            'tenant_id' => null,
            'name' => 'Platform Super Admin',
            'slug' => 'platform_super_admin_'.uniqid(),
            'scope' => 'platform',
            'organization_type' => null,
            'is_system' => true,
        ]);

        return User::factory()->create([
            'role' => 'admin',
            'email' => 'padmin.'.uniqid('', true).'@example.com',
            'platform_role_id' => $platformRole->id,
        ]);
    }

    public function test_staff_follow_parent_org_and_students_not_blocked(): void
    {
        $context = $this->createOrg('company');
        app(SubscriptionAccessService::class)->forceExpired(
            $context['organization']->subscription,
            $context['organization'],
            $context['user'],
            'expire'
        );

        $staff = User::factory()->create(['role' => 'company', 'email' => 'staff.'.uniqid().'@example.com']);
        $role = Role::query()->where('tenant_id', $context['organization']->id)->first();
        OrganizationMembership::query()->create([
            'organization_id' => $context['organization']->id,
            'user_id' => $staff->id,
            'role_id' => $role->id,
            'status' => 'active',
            'title' => 'Staff',
        ]);

        Sanctum::actingAs($staff);
        $this->postJson('/api/internships', [
            'title' => 'Staff blocked',
            'description' => 'x',
            'slots' => 1,
        ])->assertStatus(402);

        $student = User::factory()->create(['role' => 'student', 'email' => 'stu.'.uniqid().'@example.com']);
        Student::query()->create([
            'user_id' => $student->id,
            'organization_id' => $context['organization']->id,
            'school_name' => 'Test',
            'course' => 'BSIT',
            'year_level' => '4th Year',
        ]);
        Sanctum::actingAs($student);
        $this->getJson('/api/auth/me')->assertOk();
        $this->postJson('/api/saved-internships', ['internship_id' => 1])->assertStatus(422); // validation, not 402
    }

    public function test_platform_admin_not_blocked(): void
    {
        Sanctum::actingAs($this->makePlatformAdmin());
        $this->getJson('/api/admin/subscriptions')->assertOk();
    }

    public function test_admin_extend_and_activate_with_audit(): void
    {
        $context = $this->createOrg('company');
        app(SubscriptionAccessService::class)->forceExpired(
            $context['organization']->subscription,
            $context['organization'],
            $context['user'],
            'expire'
        );

        Sanctum::actingAs($this->makePlatformAdmin());

        $this->postJson('/api/admin/subscriptions/'.$context['organization']->id.'/extend-trial', [
            'extraDays' => 10,
            'reason' => 'Panel demo extension',
        ])->assertOk()->assertJsonPath('data.access.status', 'trial');

        $this->assertDatabaseHas('subscription_audit_logs', [
            'organization_id' => $context['organization']->id,
            'event' => 'trial_extended',
        ]);

        $this->postJson('/api/admin/subscriptions/'.$context['organization']->id.'/activate', [
            'plan' => 'standard',
            'reason' => 'Manual activation for demo',
        ])->assertOk()->assertJsonPath('data.access.status', 'active');

        $this->assertTrue(
            SubscriptionAuditLog::query()
                ->where('organization_id', $context['organization']->id)
                ->where('event', 'plan_activated')
                ->exists()
        );

        Sanctum::actingAs($context['user']);
        // Write should work again (may fail validation but not 402).
        $response = $this->postJson('/api/internships', [
            'title' => 'Restored posting',
            'description' => 'After activation',
            'slots' => 1,
        ]);
        $this->assertNotEquals(402, $response->status());
    }

    public function test_paid_orgs_untouched_and_free_get_trial_via_helper(): void
    {
        $paid = $this->createOrg('company');
        app(SubscriptionAccessService::class)->activatePaid(
            $paid['organization']->subscription,
            'premium',
            $paid['organization'],
            $paid['user'],
            'monthly',
            now()->addYear(),
            'already paid'
        );
        $before = $paid['organization']->fresh('subscription')->subscription->plan;

        $legacy = Subscription::query()->create([
            'plan' => 'free',
            'status' => 'active',
            'billing_cycle' => 'monthly',
        ]);
        $legacyOrg = Organization::query()->create([
            'name' => 'Legacy Free',
            'type' => 'school',
            'subscription_id' => $legacy->id,
            'owner_user_id' => User::factory()->create(['role' => 'school'])->id,
            'is_active' => true,
            'settings' => [],
        ]);
        app(SubscriptionAccessService::class)->startTrial($legacy, $legacyOrg);

        $this->assertSame('premium', $paid['organization']->fresh('subscription')->subscription->plan);
        $this->assertSame($before, 'premium');
        $this->assertSame('trialing', $legacyOrg->fresh('subscription')->subscription->status);
        $this->assertSame('standard', $legacyOrg->fresh('subscription')->subscription->plan);
    }

    public function test_switch_to_free_rejected(): void
    {
        $context = $this->createOrg('company');
        Sanctum::actingAs($context['user']);
        $this->postJson('/api/subscriptions/free')->assertStatus(422)->assertJsonPath('code', 'plan_not_selectable');
    }
}
