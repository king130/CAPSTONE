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
            'subscription.trial_days' => 30,
            'subscription.grace_days' => 7,
            'subscription.trial_plan' => 'standard',
            'subscription.timezone' => 'Asia/Manila',
        ]);

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
    private function createOrg(string $type = 'company', string $verificationStatus = 'approved'): array
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
            'plan' => 'free',
            'status' => 'active',
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
                'verification_status' => $verificationStatus,
            ]);
        } else {
            Company::query()->create([
                'user_id' => $user->id,
                'organization_id' => $organization->id,
                'subscription_id' => $subscription->id,
                'company_name' => $organization->name,
                'company_type' => $type === 'municipal_hall' ? 'municipal_hall' : null,
                'verification_status' => $verificationStatus,
            ]);
        }

        return compact('user', 'organization', 'subscription');
    }

    public function test_new_org_of_each_type_starts_trial_not_expired(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-04 10:00:00', 'Asia/Manila'));
        foreach (['school', 'company', 'municipal_hall'] as $type) {
            $context = $this->createOrg($type, 'approved');
            $subscription = $context['organization']->fresh('subscription')->subscription;
            $access = app(SubscriptionAccessService::class)->resolve($context['organization']->fresh('subscription'));
            $this->assertSame('trial', $access['status'], $type);
            $this->assertTrue($access['canWrite'], $type);
            $this->assertSame('standard', $access['plan'], $type);
            $this->assertNotNull($subscription->trial_ends_at, $type);
            $this->assertSame(30, $access['daysRemaining'], $type);
        }
        Carbon::setTestNow();
    }

    public function test_unverified_org_is_not_expired(): void
    {
        foreach (['school', 'company', 'municipal_hall'] as $type) {
            $context = $this->createOrg($type, 'pending');
            $access = app(SubscriptionAccessService::class)->resolve($context['organization']->fresh('subscription'));
            $this->assertSame('unverified', $access['status'], $type);
            $this->assertTrue($access['canWrite'], $type);
            $this->assertSame('free', $access['plan'], $type);
            $this->assertNotSame('expired', $access['status'], $type);
            $this->assertNotNull($access['trialEndsAt'], $type);
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

    public function test_paid_period_transitions_past_due_then_expired(): void
    {
        $access = app(SubscriptionAccessService::class);
        $context = $this->createOrg('school');
        $subscription = $context['organization']->subscription;

        Carbon::setTestNow(Carbon::parse('2026-10-18 10:00:00', 'Asia/Manila'));
        $access->activatePaid(
            $subscription,
            'standard',
            $context['organization'],
            $context['user'],
            'monthly',
            Carbon::parse('2026-11-01 23:59:59', 'Asia/Manila'),
            'test activate'
        );

        $this->assertSame('active', $access->resolve($context['organization']->fresh('subscription'))['status']);

        // Inside grace after paid end.
        Carbon::setTestNow(Carbon::parse('2026-11-02 10:00:00', 'Asia/Manila'));
        $pastDue = $access->resolve($context['organization']->fresh('subscription'));
        $this->assertSame('past_due', $pastDue['status']);
        $this->assertTrue($pastDue['canWrite']);

        // After grace → expired.
        Carbon::setTestNow(Carbon::parse('2026-11-10 10:00:00', 'Asia/Manila'));
        $state = $access->resolve($context['organization']->fresh('subscription'));
        $this->assertSame('expired', $state['status']);
        $this->assertFalse($state['canWrite']);
        Carbon::setTestNow();
    }

    public function test_never_paid_trial_expires_after_grace(): void
    {
        $access = app(SubscriptionAccessService::class);
        $context = $this->createOrg('company');
        $subscription = $context['organization']->subscription;

        Carbon::setTestNow(Carbon::parse('2026-11-04 10:00:00', 'Asia/Manila'));
        $subscription->forceFill([
            'plan' => 'standard',
            'status' => 'trialing',
            'trial_ends_at' => Carbon::parse('2026-11-01 10:00:00', 'Asia/Manila'),
            'grace_ends_at' => Carbon::parse('2026-11-08 10:00:00', 'Asia/Manila'),
            'ends_at' => null,
        ])->save();

        $pastDue = $access->resolve($context['organization']->fresh('subscription'));
        $this->assertSame('past_due', $pastDue['status']);
        $this->assertTrue($pastDue['canWrite']);

        Carbon::setTestNow(Carbon::parse('2026-11-10 10:00:00', 'Asia/Manila'));
        $expired = $access->resolve($context['organization']->fresh('subscription'));
        $this->assertSame('expired', $expired['status']);
        $this->assertFalse($expired['canWrite']);
        Carbon::setTestNow();
    }

    public function test_pending_payment_status(): void
    {
        $context = $this->createOrg('company');
        $org = $context['organization'];
        $org->forceFill([
            'settings' => array_merge($org->settings ?? [], [
                'pending_plan_change' => [
                    'previousPlan' => 'free',
                    'targetPlan' => 'standard',
                    'previousStatus' => 'active',
                ],
            ]),
        ])->save();

        $state = app(SubscriptionAccessService::class)->resolve($org->fresh('subscription'));
        $this->assertSame('pending_payment', $state['status']);
        $this->assertTrue($state['canWrite']);
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

            $this->getJson('/api/auth/me')->assertOk()
                ->assertJsonPath('subscriptionAccess.status', 'expired')
                ->assertJsonPath('subscriptionAccess.canWrite', false);
            $this->getJson('/api/internships')->assertOk();
        }
    }

    public function test_verification_and_billing_writable_when_expired(): void
    {
        $context = $this->createOrg('company', 'approved');
        app(SubscriptionAccessService::class)->forceExpired(
            $context['organization']->subscription,
            $context['organization'],
            $context['user'],
            'expire'
        );

        Sanctum::actingAs($context['user']);

        // Billing cancel-pending must not 402 (ok or validation — never subscription lock).
        $cancel = $this->postJson('/api/subscriptions/cancel-pending');
        $this->assertNotEquals(402, $cancel->status());

        // Profile stays writable.
        $this->patchJson('/api/profile', ['name' => 'Updated Name'])->assertOk();
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
        $this->postJson('/api/saved-internships', ['internship_id' => 1])->assertStatus(422);
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
        $response = $this->postJson('/api/internships', [
            'title' => 'Restored posting',
            'description' => 'After activation',
            'slots' => 1,
        ]);
        $this->assertNotEquals(402, $response->status());
    }

    public function test_paid_orgs_untouched_and_free_get_free_via_helper(): void
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
        $this->assertNotNull($legacyOrg->fresh('subscription')->subscription->trial_ends_at);
    }

    public function test_switch_to_free_rejected(): void
    {
        $context = $this->createOrg('company');
        Sanctum::actingAs($context['user']);
        $this->postJson('/api/subscriptions/free')->assertStatus(422)->assertJsonPath('code', 'plan_not_selectable');
    }
}
