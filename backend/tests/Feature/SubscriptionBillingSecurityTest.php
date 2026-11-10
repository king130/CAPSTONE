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
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SubscriptionBillingSecurityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{user: User, organization: Organization, subscription: Subscription, role: Role}
     */
    private function createOrgMember(string $withPermissionKey = 'org.manage_subscription'): array
    {
        Permission::query()->updateOrCreate(
            ['key' => 'org.manage_subscription'],
            ['scope' => 'organization', 'description' => 'Manage organization subscription']
        );
        Permission::query()->updateOrCreate(
            ['key' => 'manage_subscription'],
            ['scope' => 'organization', 'description' => 'Legacy manage subscription']
        );

        $user = User::factory()->create([
            'role' => 'company',
            'email' => 'billing.'.uniqid('', true).'@example.com',
        ]);

        $subscription = Subscription::query()->create([
            'plan' => 'standard',
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->addYear()->toDateString(),
        ]);

        $organization = Organization::query()->create([
            'name' => 'Billing Test Org',
            'type' => 'company',
            'subscription_id' => $subscription->id,
            'owner_user_id' => $user->id,
            'settings' => [],
            'is_active' => true,
        ]);
        $this->ensureOrganizationSubscriptionWritable($organization);

        $role = Role::query()->create([
            'tenant_id' => $organization->id,
            'name' => 'Billing Role',
            'slug' => 'billing_role_'.uniqid(),
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

        Company::query()->create([
            'user_id' => $user->id,
            'organization_id' => $organization->id,
            'company_name' => 'Billing Test Company',
            'verification_status' => 'approved',
        ]);

        return compact('user', 'organization', 'subscription', 'role');
    }

    public function test_profile_rejects_subscription_mutation(): void
    {
        $context = $this->createOrgMember();
        Sanctum::actingAs($context['user']);

        $response = $this->patchJson('/api/profile', [
            'subscription' => [
                'plan' => 'premium',
                'status' => 'active',
                'billingCycle' => 'yearly',
                'pendingChange' => ['targetPlan' => 'premium'],
                'pendingPayment' => ['method' => 'gcash'],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment([
            'message' => 'Subscription changes must be made through the billing endpoints.',
        ]);

        $context['subscription']->refresh();
        $context['organization']->refresh();
        $this->assertSame('standard', $context['subscription']->plan);
        $this->assertSame('active', $context['subscription']->status);
        $this->assertSame('monthly', $context['subscription']->billing_cycle);
        $this->assertArrayNotHasKey('pending_plan_change', $context['organization']->settings ?? []);
    }

    public function test_member_without_manage_subscription_cannot_switch_to_free(): void
    {
        $context = $this->createOrgMember('');
        Sanctum::actingAs($context['user']);

        $response = $this->postJson('/api/subscriptions/free');

        // Free plan is no longer selectable (Phase H); endpoint rejects for everyone.
        $response->assertStatus(422);
        $response->assertJsonPath('code', 'plan_not_selectable');
        $context['subscription']->refresh();
        $this->assertSame('standard', $context['subscription']->plan);
    }

    public function test_member_without_manage_subscription_cannot_cancel_pending(): void
    {
        $context = $this->createOrgMember('');
        $context['organization']->settings = [
            'pending_plan_change' => [
                'requestId' => 'req_1',
                'targetPlan' => 'premium',
            ],
        ];
        $context['organization']->save();

        Sanctum::actingAs($context['user']);

        $response = $this->postJson('/api/subscriptions/cancel-pending');

        $response->assertForbidden();
        $context['organization']->refresh();
        $this->assertSame('premium', $context['organization']->settings['pending_plan_change']['targetPlan'] ?? null);
    }

    public function test_authorized_user_cannot_switch_to_free_plan(): void
    {
        $context = $this->createOrgMember();
        $context['organization']->settings = [
            'pending_plan_change' => ['requestId' => 'req_free', 'targetPlan' => 'premium'],
        ];
        $context['organization']->save();

        Sanctum::actingAs($context['user']);

        $response = $this->postJson('/api/subscriptions/free');

        $response->assertStatus(422);
        $response->assertJsonPath('code', 'plan_not_selectable');
        $context['subscription']->refresh();
        $this->assertSame('standard', $context['subscription']->plan);
        $this->assertNotSame('free', $context['subscription']->plan);
    }

    public function test_free_endpoint_rejects_even_with_client_paid_plan_payload(): void
    {
        $context = $this->createOrgMember();
        Sanctum::actingAs($context['user']);

        $response = $this->postJson('/api/subscriptions/free', [
            'plan' => 'premium',
            'status' => 'active',
            'subscription' => ['plan' => 'premium', 'status' => 'active'],
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('code', 'plan_not_selectable');
        $context['subscription']->refresh();
        $this->assertSame('standard', $context['subscription']->plan);
    }

    public function test_cancel_pending_preserves_active_plan(): void
    {
        $context = $this->createOrgMember();
        $context['subscription']->update([
            'plan' => 'standard',
            'status' => 'active',
            'billing_cycle' => 'monthly',
        ]);
        $context['organization']->settings = [
            'pending_plan_change' => [
                'requestId' => 'req_active',
                'targetPlan' => 'premium',
                'checkoutSessionId' => 'cs_test',
            ],
        ];
        $context['organization']->save();

        Sanctum::actingAs($context['user']);

        $response = $this->postJson('/api/subscriptions/cancel-pending');

        $response->assertOk();
        $context['subscription']->refresh();
        $context['organization']->refresh();
        $this->assertSame('standard', $context['subscription']->plan);
        $this->assertSame('active', $context['subscription']->status);
        $this->assertArrayNotHasKey('pending_plan_change', $context['organization']->settings ?? []);
    }

    public function test_cancel_pending_returns_unpaid_pending_subscription_to_trial(): void
    {
        $context = $this->createOrgMember();
        $context['subscription']->update([
            'plan' => 'free',
            'status' => 'pending',
            'billing_cycle' => 'monthly',
            'trial_started_at' => null,
            'trial_ends_at' => null,
            'ends_at' => null,
        ]);
        $context['organization']->settings = [
            'pending_plan_change' => [
                'requestId' => 'req_pending',
                'targetPlan' => 'standard',
                'previousPlan' => 'free',
                'previousStatus' => 'active',
            ],
        ];
        $context['organization']->save();

        Sanctum::actingAs($context['user']);

        $response = $this->postJson('/api/subscriptions/cancel-pending');

        $response->assertOk();
        $context['subscription']->refresh();
        $context['organization']->refresh();
        // Cancel restores free plan (writable free limits), not a paid trial.
        $this->assertSame('free', $context['subscription']->plan);
        $this->assertSame('active', $context['subscription']->status);
        $this->assertSame('monthly', $context['subscription']->billing_cycle);
        $this->assertArrayNotHasKey('pending_plan_change', $context['organization']->settings ?? []);
    }

    public function test_billing_user_cannot_modify_another_organization(): void
    {
        $orgA = $this->createOrgMember();
        $orgB = $this->createOrgMember();

        $orgB['subscription']->update(['plan' => 'premium', 'status' => 'active']);
        $orgB['organization']->settings = [
            'pending_plan_change' => ['requestId' => 'other_org', 'targetPlan' => 'standard'],
        ];
        $orgB['organization']->save();

        Sanctum::actingAs($orgA['user']);

        $this->postJson('/api/subscriptions/free')->assertStatus(422);
        $this->postJson('/api/subscriptions/cancel-pending')->assertOk();

        $orgB['subscription']->refresh();
        $orgB['organization']->refresh();
        $this->assertSame('premium', $orgB['subscription']->plan);
        $this->assertSame('active', $orgB['subscription']->status);
        $this->assertSame('other_org', $orgB['organization']->settings['pending_plan_change']['requestId'] ?? null);
    }

    public function test_unauthorized_member_cannot_start_checkout(): void
    {
        $context = $this->createOrgMember('');
        $this->ensurePremiumPlanDefinition();
        config(['services.paymongo.secret_key' => 'sk_test_fake']);
        Http::fake();

        Sanctum::actingAs($context['user']);

        $response = $this->postJson('/api/subscriptions/checkout', [
            'requestId' => 'req_unauth_checkout',
            'planId' => 'premium',
            'role' => 'company',
            'billingCycle' => 'monthly',
        ]);

        $response->assertForbidden();
        Http::assertNothingSent();
        $context['organization']->refresh();
        $context['subscription']->refresh();
        $this->assertArrayNotHasKey('pending_plan_change', $context['organization']->settings ?? []);
        $this->assertSame('standard', $context['subscription']->plan);
        $this->assertSame('active', $context['subscription']->status);
    }

    public function test_unauthorized_member_cannot_verify_checkout(): void
    {
        $context = $this->createOrgMember('');
        $pending = [
            'requestId' => 'req_unauth_verify',
            'targetPlan' => 'premium',
            'checkoutSessionId' => 'cs_unauth',
        ];
        $context['organization']->settings = ['pending_plan_change' => $pending];
        $context['organization']->save();
        config(['services.paymongo.secret_key' => 'sk_test_fake']);
        Http::fake();

        Sanctum::actingAs($context['user']);

        $response = $this->postJson('/api/subscriptions/verify', [
            'requestId' => 'req_unauth_verify',
            'checkoutSessionId' => 'cs_unauth',
        ]);

        $response->assertForbidden();
        Http::assertNothingSent();
        $context['organization']->refresh();
        $context['subscription']->refresh();
        $this->assertSame('premium', $context['organization']->settings['pending_plan_change']['targetPlan'] ?? null);
        $this->assertSame('standard', $context['subscription']->plan);
        $this->assertSame('active', $context['subscription']->status);
    }

    public function test_paymongo_not_configured_checkout_leaves_free_org_unchanged(): void
    {
        $context = $this->createOrgMember();
        $this->ensurePremiumPlanDefinition();
        $this->ensureStandardPlanDefinition();
        $context['subscription']->update([
            'plan' => 'free',
            'status' => 'active',
            'billing_cycle' => 'monthly',
        ]);
        config(['services.paymongo.secret_key' => '']);

        Sanctum::actingAs($context['user']);
        $response = $this->postJson('/api/subscriptions/checkout', [
            'requestId' => 'req_no_paymongo_free',
            'planId' => 'premium',
            'role' => 'company',
            'billingCycle' => 'monthly',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('code', 'paymongo_not_configured');
        $this->assertStringContainsString('Payment is not connected', (string) $response->json('message'));

        $context['subscription']->refresh();
        $context['organization']->refresh();
        $this->assertSame('free', $context['subscription']->plan);
        $this->assertSame('active', $context['subscription']->status);
        $this->assertArrayNotHasKey('pending_plan_change', $context['organization']->settings ?? []);
        $this->assertSame(
            'free',
            app(\App\Services\SubscriptionPlanService::class)
                ->getPlanForOrganization($context['organization']->fresh(['subscription']))
                ?->slug
        );
    }

    public function test_paymongo_not_configured_checkout_leaves_active_paid_org_unchanged(): void
    {
        $context = $this->createOrgMember();
        $this->ensurePremiumPlanDefinition();
        $this->ensureStandardPlanDefinition();
        $context['subscription']->update([
            'plan' => 'standard',
            'status' => 'active',
            'billing_cycle' => 'monthly',
        ]);
        config(['services.paymongo.secret_key' => '']);

        Sanctum::actingAs($context['user']);
        $response = $this->postJson('/api/subscriptions/checkout', [
            'requestId' => 'req_no_paymongo_paid',
            'planId' => 'premium',
            'role' => 'company',
            'billingCycle' => 'monthly',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('code', 'paymongo_not_configured');

        $context['subscription']->refresh();
        $context['organization']->refresh();
        $this->assertSame('standard', $context['subscription']->plan);
        $this->assertSame('active', $context['subscription']->status);
        $this->assertArrayNotHasKey('pending_plan_change', $context['organization']->settings ?? []);
        $effective = app(\App\Services\SubscriptionPlanService::class)
            ->getPlanForOrganization($context['organization']->fresh(['subscription']));
        $this->assertSame('standard', $effective?->slug);
        $this->assertSame(10, (int) $effective?->company_internships_limit);
    }

    public function test_paymongo_not_configured_verify_does_not_activate_plan(): void
    {
        $context = $this->createOrgMember();
        $this->ensurePremiumPlanDefinition();
        $this->ensureStandardPlanDefinition();
        $context['subscription']->update([
            'plan' => 'free',
            'status' => 'active',
            'billing_cycle' => 'monthly',
        ]);
        config(['services.paymongo.secret_key' => '']);

        Sanctum::actingAs($context['user']);
        $response = $this->postJson('/api/subscriptions/verify', [
            'requestId' => 'req_no_paymongo_verify',
            'checkoutSessionId' => 'cs_missing',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('code', 'paymongo_not_configured');
        $context['subscription']->refresh();
        $this->assertSame('free', $context['subscription']->plan);
        $this->assertSame('active', $context['subscription']->status);
    }

    public function test_authorized_user_can_start_checkout(): void
    {
        $context = $this->createOrgMember();
        $this->ensurePremiumPlanDefinition();
        $this->ensureStandardPlanDefinition();
        config(['services.paymongo.secret_key' => 'sk_test_fake']);
        Http::fake([
            'https://api.paymongo.com/v1/checkout_sessions' => Http::response([
                'data' => [
                    'id' => 'cs_auth_checkout',
                    'attributes' => [
                        'checkout_url' => 'https://checkout.paymongo.test/cs_auth_checkout',
                        'status' => 'active',
                    ],
                ],
            ], 200),
        ]);

        Sanctum::actingAs($context['user']);

        $response = $this->postJson('/api/subscriptions/checkout', [
            'requestId' => 'req_auth_checkout',
            'planId' => 'premium',
            'role' => 'company',
            'billingCycle' => 'monthly',
        ]);

        $response->assertOk();
        $response->assertJsonPath('checkoutSessionId', 'cs_auth_checkout');
        Http::assertSent(fn ($request) => $request->url() === 'https://api.paymongo.com/v1/checkout_sessions');

        $context['organization']->refresh();
        $context['subscription']->refresh();
        $this->assertSame('req_auth_checkout', $context['organization']->settings['pending_plan_change']['requestId'] ?? null);
        $this->assertSame('premium', $context['organization']->settings['pending_plan_change']['targetPlan'] ?? null);
        $this->assertSame('standard', $context['organization']->settings['pending_plan_change']['previousPlan'] ?? null);
        // Existing paid orgs keep active status while checkout is pending.
        $this->assertSame('standard', $context['subscription']->plan);
        $this->assertSame('active', $context['subscription']->status);

        $effective = app(\App\Services\SubscriptionPlanService::class)
            ->getPlanForOrganization($context['organization']->fresh(['subscription']));
        $this->assertSame('standard', $effective?->slug);
        $this->assertSame(10, (int) $effective?->company_internships_limit);
        $this->assertNotSame(3, (int) $effective?->company_internships_limit);
    }

    public function test_active_standard_pending_premium_keeps_standard_limits(): void
    {
        $context = $this->createOrgMember();
        $this->ensurePremiumPlanDefinition();
        $this->ensureStandardPlanDefinition();
        $context['subscription']->update([
            'plan' => 'standard',
            'status' => 'active',
            'billing_cycle' => 'monthly',
        ]);
        $context['organization']->settings = [
            'pending_plan_change' => [
                'requestId' => 'req_std_pending_limits',
                'targetPlan' => 'premium',
                'previousPlan' => 'standard',
                'previousStatus' => 'active',
                'previousBillingCycle' => 'monthly',
                'checkoutSessionId' => 'cs_std_pending_limits',
            ],
        ];
        $context['organization']->save();

        $effective = app(\App\Services\SubscriptionPlanService::class)
            ->getPlanForOrganization($context['organization']->fresh(['subscription']));

        $this->assertSame('standard', $effective?->slug);
        $this->assertSame(10, (int) $effective?->company_internships_limit);
        $this->assertSame(5, (int) $effective?->company_accounts_limit);
        $this->assertNotSame(50, (int) $effective?->company_internships_limit);
    }

    public function test_active_premium_pending_paid_change_keeps_premium_limits(): void
    {
        $context = $this->createOrgMember();
        $this->ensurePremiumPlanDefinition();
        $this->ensureStandardPlanDefinition();
        $context['subscription']->update([
            'plan' => 'premium',
            'status' => 'active',
            'billing_cycle' => 'monthly',
        ]);
        $context['organization']->settings = [
            'pending_plan_change' => [
                'requestId' => 'req_prem_pending_limits',
                'targetPlan' => 'standard',
                'previousPlan' => 'premium',
                'previousStatus' => 'active',
                'previousBillingCycle' => 'monthly',
                'checkoutSessionId' => 'cs_prem_pending_limits',
            ],
        ];
        $context['organization']->save();

        $effective = app(\App\Services\SubscriptionPlanService::class)
            ->getPlanForOrganization($context['organization']->fresh(['subscription']));

        $this->assertSame('premium', $effective?->slug);
        $this->assertSame(50, (int) $effective?->company_internships_limit);
        $this->assertNotSame(10, (int) $effective?->company_internships_limit);
    }

    public function test_free_pending_paid_signup_keeps_free_limits(): void
    {
        $context = $this->createOrgMember();
        $this->ensurePremiumPlanDefinition();
        $this->ensureStandardPlanDefinition();
        $context['subscription']->update([
            'plan' => 'free',
            'status' => 'pending',
            'billing_cycle' => 'monthly',
        ]);
        $context['organization']->settings = [
            'pending_plan_change' => [
                'requestId' => 'req_free_pending_limits',
                'targetPlan' => 'premium',
                'previousPlan' => 'free',
                'previousStatus' => 'active',
                'previousBillingCycle' => 'monthly',
                'checkoutSessionId' => 'cs_free_pending_limits',
            ],
        ];
        $context['organization']->save();

        $effective = app(\App\Services\SubscriptionPlanService::class)
            ->getPlanForOrganization($context['organization']->fresh(['subscription']));

        $this->assertSame('free', $effective?->slug);
        $this->assertSame(3, (int) $effective?->company_internships_limit);
        $this->assertNotSame(50, (int) $effective?->company_internships_limit);
    }

    public function test_legacy_free_org_checkout_cancel_starts_trial(): void
    {
        $context = $this->createOrgMember();
        $this->ensurePremiumPlanDefinition();
        $this->ensureStandardPlanDefinition();
        $context['subscription']->update([
            'plan' => 'free',
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'ends_at' => null,
            'trial_started_at' => null,
            'trial_ends_at' => null,
            'grace_ends_at' => null,
        ]);
        config(['services.paymongo.secret_key' => 'sk_test_fake']);
        Http::fake([
            'https://api.paymongo.com/v1/checkout_sessions' => Http::response([
                'data' => [
                    'id' => 'cs_free_cancel',
                    'attributes' => [
                        'checkout_url' => 'https://checkout.paymongo.test/cs_free_cancel',
                        'status' => 'active',
                    ],
                ],
            ], 200),
        ]);

        Sanctum::actingAs($context['user']);
        $this->postJson('/api/subscriptions/checkout', [
            'requestId' => 'req_free_cancel',
            'planId' => 'standard',
            'role' => 'company',
            'billingCycle' => 'monthly',
        ])->assertOk();

        $context['subscription']->refresh();
        // Phase H: unpaid checkout no longer flips an active/trial row to pending.
        $this->assertContains($context['subscription']->status, ['active', 'pending', 'trialing']);
        $this->assertSame('free', $context['subscription']->plan);

        $this->postJson('/api/subscriptions/cancel-pending')->assertOk();
        $context['subscription']->refresh();
        $context['organization']->refresh();
        // Cancel restores free plan with free seat limits.
        $this->assertSame('active', $context['subscription']->status);
        $this->assertSame('free', $context['subscription']->plan);
        $this->assertArrayNotHasKey('pending_plan_change', $context['organization']->settings ?? []);
        $this->assertSame(
            'free',
            app(\App\Services\SubscriptionPlanService::class)
                ->getPlanForOrganization($context['organization']->fresh(['subscription']))
                ?->slug
        );
    }

    public function test_standard_org_checkout_cancel_remains_standard(): void
    {
        $context = $this->createOrgMember();
        $this->ensurePremiumPlanDefinition();
        $this->ensureStandardPlanDefinition();
        config(['services.paymongo.secret_key' => 'sk_test_fake']);
        Http::fake([
            'https://api.paymongo.com/v1/checkout_sessions' => Http::response([
                'data' => [
                    'id' => 'cs_standard_cancel',
                    'attributes' => [
                        'checkout_url' => 'https://checkout.paymongo.test/cs_standard_cancel',
                        'status' => 'active',
                    ],
                ],
            ], 200),
        ]);

        Sanctum::actingAs($context['user']);
        $this->postJson('/api/subscriptions/checkout', [
            'requestId' => 'req_standard_cancel',
            'planId' => 'premium',
            'role' => 'company',
            'billingCycle' => 'monthly',
        ])->assertOk();

        $context['subscription']->refresh();
        $this->assertSame('standard', $context['subscription']->plan);
        $this->assertSame('active', $context['subscription']->status);

        $this->postJson('/api/subscriptions/cancel-pending')->assertOk();
        $context['subscription']->refresh();
        $context['organization']->refresh();
        $this->assertSame('standard', $context['subscription']->plan);
        $this->assertSame('active', $context['subscription']->status);
        $this->assertArrayNotHasKey('pending_plan_change', $context['organization']->settings ?? []);
        $effective = app(\App\Services\SubscriptionPlanService::class)
            ->getPlanForOrganization($context['organization']->fresh(['subscription']));
        $this->assertSame('standard', $effective?->slug);
        $this->assertSame(10, (int) $effective?->company_internships_limit);
        $this->assertNotSame(3, (int) $effective?->company_internships_limit);
    }

    public function test_premium_org_checkout_cancel_remains_premium(): void
    {
        $context = $this->createOrgMember();
        $this->ensurePremiumPlanDefinition();
        $this->ensureStandardPlanDefinition();
        $context['subscription']->update([
            'plan' => 'premium',
            'status' => 'active',
            'billing_cycle' => 'monthly',
        ]);
        config(['services.paymongo.secret_key' => 'sk_test_fake']);
        Http::fake([
            'https://api.paymongo.com/v1/checkout_sessions' => Http::response([
                'data' => [
                    'id' => 'cs_premium_cancel',
                    'attributes' => [
                        'checkout_url' => 'https://checkout.paymongo.test/cs_premium_cancel',
                        'status' => 'active',
                    ],
                ],
            ], 200),
        ]);

        Sanctum::actingAs($context['user']);
        $this->postJson('/api/subscriptions/checkout', [
            'requestId' => 'req_premium_cancel',
            'planId' => 'standard',
            'role' => 'company',
            'billingCycle' => 'monthly',
        ])->assertOk();

        $this->postJson('/api/subscriptions/cancel-pending')->assertOk();
        $context['subscription']->refresh();
        $context['organization']->refresh();
        $this->assertSame('premium', $context['subscription']->plan);
        $this->assertSame('active', $context['subscription']->status);
        $this->assertArrayNotHasKey('pending_plan_change', $context['organization']->settings ?? []);
        $this->assertSame(
            'premium',
            app(\App\Services\SubscriptionPlanService::class)
                ->getPlanForOrganization($context['organization']->fresh(['subscription']))
                ?->slug
        );
    }

    public function test_legacy_pending_paid_plan_cancel_does_not_demote_to_free(): void
    {
        $context = $this->createOrgMember();
        $this->ensureStandardPlanDefinition();
        // Legacy create() flipped status to pending while leaving the paid plan slug.
        $context['subscription']->update([
            'plan' => 'standard',
            'status' => 'pending',
            'billing_cycle' => 'monthly',
        ]);
        $context['organization']->settings = [
            'pending_plan_change' => [
                'requestId' => 'req_legacy_pending',
                'targetPlan' => 'premium',
                'checkoutSessionId' => 'cs_legacy',
            ],
        ];
        $context['organization']->save();

        Sanctum::actingAs($context['user']);
        $this->postJson('/api/subscriptions/cancel-pending')->assertOk();

        $context['subscription']->refresh();
        $context['organization']->refresh();
        $this->assertSame('standard', $context['subscription']->plan);
        $this->assertSame('active', $context['subscription']->status);
        $this->assertArrayNotHasKey('pending_plan_change', $context['organization']->settings ?? []);
    }

    public function test_authorized_user_can_verify_paid_checkout(): void
    {
        $context = $this->createOrgMember();
        $this->ensurePremiumPlanDefinition();
        $this->ensureStandardPlanDefinition();
        $context['subscription']->update([
            'plan' => 'free',
            'status' => 'pending',
            'billing_cycle' => 'monthly',
        ]);
        $context['organization']->settings = [
            'pending_plan_change' => [
                'requestId' => 'req_auth_verify',
                'targetPlan' => 'premium',
                'previousPlan' => 'free',
                'checkoutSessionId' => 'cs_auth_verify',
                'billingCycle' => 'monthly',
            ],
        ];
        $context['organization']->save();
        config(['services.paymongo.secret_key' => 'sk_test_fake']);
        Http::fake([
            'https://api.paymongo.com/v1/checkout_sessions/cs_auth_verify' => Http::response([
                'data' => [
                    'id' => 'cs_auth_verify',
                    'attributes' => [
                        'status' => 'paid',
                        'payments' => [
                            ['attributes' => ['status' => 'paid']],
                        ],
                    ],
                ],
            ], 200),
        ]);

        Sanctum::actingAs($context['user']);

        $response = $this->postJson('/api/subscriptions/verify', [
            'requestId' => 'req_auth_verify',
            'checkoutSessionId' => 'cs_auth_verify',
        ]);

        $response->assertOk();
        $response->assertJsonPath('verified', true);
        $context['subscription']->refresh();
        $context['organization']->refresh();
        $this->assertSame('premium', $context['subscription']->plan);
        $this->assertSame('active', $context['subscription']->status);
        $this->assertArrayNotHasKey('pending_plan_change', $context['organization']->settings ?? []);

        $effective = app(\App\Services\SubscriptionPlanService::class)
            ->getPlanForOrganization($context['organization']->fresh(['subscription']));
        $this->assertSame('premium', $effective?->slug);
        $this->assertSame(50, (int) $effective?->company_internships_limit);
    }

    public function test_authorized_user_cannot_activate_unpaid_checkout(): void
    {
        $context = $this->createOrgMember();
        $context['subscription']->update([
            'plan' => 'free',
            'status' => 'pending',
            'billing_cycle' => 'monthly',
        ]);
        $context['organization']->settings = [
            'pending_plan_change' => [
                'requestId' => 'req_unpaid_verify',
                'targetPlan' => 'premium',
                'checkoutSessionId' => 'cs_unpaid_verify',
                'billingCycle' => 'monthly',
            ],
        ];
        $context['organization']->save();
        config(['services.paymongo.secret_key' => 'sk_test_fake']);
        Http::fake([
            'https://api.paymongo.com/v1/checkout_sessions/cs_unpaid_verify' => Http::response([
                'data' => [
                    'id' => 'cs_unpaid_verify',
                    'attributes' => [
                        'status' => 'active',
                        'payments' => [],
                    ],
                ],
            ], 200),
        ]);

        Sanctum::actingAs($context['user']);

        $response = $this->postJson('/api/subscriptions/verify', [
            'requestId' => 'req_unpaid_verify',
            'checkoutSessionId' => 'cs_unpaid_verify',
        ]);

        $response->assertOk();
        $response->assertJsonPath('verified', false);
        $context['subscription']->refresh();
        $context['organization']->refresh();
        $this->assertSame('free', $context['subscription']->plan);
        $this->assertSame('pending', $context['subscription']->status);
        $this->assertSame('premium', $context['organization']->settings['pending_plan_change']['targetPlan'] ?? null);
    }

    private function ensurePremiumPlanDefinition(): void
    {
        SubscriptionPlanDefinition::query()->updateOrCreate(
            ['slug' => 'premium'],
            [
                'name' => 'Premium',
                'description' => 'Premium plan for billing tests',
                'school_price' => 1000,
                'company_price' => 1000,
                'school_features' => ['Feature'],
                'company_features' => ['Feature'],
                'school_coordinators_limit' => 50,
                'school_students_limit' => 500,
                'company_accounts_limit' => 50,
                'company_internships_limit' => 50,
                'is_active' => true,
            ]
        );
    }

    private function ensureStandardPlanDefinition(): void
    {
        SubscriptionPlanDefinition::query()->updateOrCreate(
            ['slug' => 'standard'],
            [
                'name' => 'Standard',
                'description' => 'Standard plan for billing tests',
                'school_price' => 500,
                'company_price' => 500,
                'school_features' => ['Feature'],
                'company_features' => ['Feature'],
                'school_coordinators_limit' => 5,
                'school_students_limit' => 100,
                'company_accounts_limit' => 5,
                'company_internships_limit' => 10,
                'is_active' => true,
            ]
        );

        SubscriptionPlanDefinition::query()->updateOrCreate(
            ['slug' => 'free'],
            [
                'name' => 'Free',
                'description' => 'Free plan for billing tests',
                'school_price' => 0,
                'company_price' => 0,
                'school_features' => ['Feature'],
                'company_features' => ['Feature'],
                'school_coordinators_limit' => 1,
                'school_students_limit' => 5,
                'company_accounts_limit' => 1,
                'company_internships_limit' => 3,
                'is_active' => true,
            ]
        );
    }
}
