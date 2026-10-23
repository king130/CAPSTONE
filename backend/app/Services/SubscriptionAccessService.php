<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\Subscription;
use App\Models\SubscriptionAuditLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Derive organization subscription access from dates (Asia/Manila).
 * Statuses: trial | active | grace | expired (exactly one).
 */
class SubscriptionAccessService
{
    public const STATUS_TRIAL = 'trial';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_GRACE = 'grace';

    public const STATUS_EXPIRED = 'expired';

    public function timezone(): string
    {
        return (string) config('subscription.timezone', 'Asia/Manila');
    }

    public function now(): Carbon
    {
        return Carbon::now($this->timezone());
    }

    public function trialDays(): int
    {
        return max(1, (int) config('subscription.trial_days', 14));
    }

    public function graceDays(): int
    {
        return max(0, (int) config('subscription.grace_days', 7));
    }

    public function trialPlanSlug(): string
    {
        return strtolower(trim((string) config('subscription.trial_plan', 'standard'))) ?: 'standard';
    }

    /**
     * @return array{
     *   status: string,
     *   canWrite: bool,
     *   canReviewDtr: bool,
     *   plan: string|null,
     *   trialEndsAt: string|null,
     *   graceEndsAt: string|null,
     *   paidEndsAt: string|null,
     *   daysRemaining: int|null,
     *   message: string,
     *   code: string
     * }
     */
    public function resolve(?Organization $organization): array
    {
        if (! $organization) {
            return $this->payload(self::STATUS_EXPIRED, false, false, null, null, null, null, null, 'No organization context.', 'subscription_missing');
        }

        $organization->loadMissing('subscription');
        $subscription = $organization->subscription;
        if (! $subscription) {
            return $this->payload(self::STATUS_EXPIRED, false, false, null, null, null, null, null, 'No subscription on this organization.', 'subscription_missing');
        }

        $now = $this->now();
        $plan = strtolower(trim((string) $subscription->plan)) ?: null;
        $rawStatus = strtolower(trim((string) $subscription->status));

        // Legacy free + active (row kept for history / seat tests). Still writable; not offered to new orgs.
        if ($rawStatus === 'active' && $plan === 'free') {
            return $this->payload(
                self::STATUS_ACTIVE,
                true,
                true,
                'free',
                $subscription->trial_ends_at?->timezone($this->timezone())->toIso8601String(),
                $subscription->grace_ends_at?->timezone($this->timezone())->toIso8601String(),
                null,
                null,
                'Legacy free plan is active. New organizations use a trial, then a paid plan.',
                'subscription_active'
            );
        }

        // Paid active with period end in the future (or open-ended).
        if ($rawStatus === 'active' && $plan && $plan !== 'free') {
            $paidEnds = $subscription->ends_at
                ? Carbon::parse($subscription->ends_at, $this->timezone())->endOfDay()
                : null;

            if ($paidEnds === null || $paidEnds->greaterThanOrEqualTo($now)) {
                $days = $paidEnds ? (int) $now->diffInDays($paidEnds, false) : null;

                return $this->payload(
                    self::STATUS_ACTIVE,
                    true,
                    true,
                    $plan,
                    $subscription->trial_ends_at?->timezone($this->timezone())->toIso8601String(),
                    $subscription->grace_ends_at?->timezone($this->timezone())->toIso8601String(),
                    $paidEnds?->toIso8601String(),
                    $days,
                    'Paid plan is active.',
                    'subscription_active'
                );
            }

            // Paid period ended — check grace (grace_ends_at or ends_at + grace_days).
            $graceEnds = $subscription->grace_ends_at
                ? Carbon::parse($subscription->grace_ends_at, $this->timezone())
                : $paidEnds->copy()->addDays($this->graceDays());

            if ($graceEnds->greaterThanOrEqualTo($now)) {
                $days = (int) $now->diffInDays($graceEnds, false);

                return $this->payload(
                    self::STATUS_GRACE,
                    true,
                    true,
                    $plan,
                    null,
                    $graceEnds->toIso8601String(),
                    $paidEnds->toIso8601String(),
                    $days,
                    'Paid period ended. You are in a grace period — upgrade soon.',
                    'subscription_grace'
                );
            }

            return $this->payload(
                self::STATUS_EXPIRED,
                false,
                false,
                $plan,
                null,
                $graceEnds->toIso8601String(),
                $paidEnds->toIso8601String(),
                0,
                'Subscription expired. Upgrade to restore write access.',
                'subscription_expired'
            );
        }

        // Trialing (or legacy free treated as trial via dates).
        if (in_array($rawStatus, ['trialing', 'trial'], true) || ($subscription->trial_ends_at && $rawStatus !== 'active')) {
            $trialEnds = $subscription->trial_ends_at
                ? Carbon::parse($subscription->trial_ends_at, $this->timezone())
                : null;

            if ($trialEnds && $trialEnds->greaterThanOrEqualTo($now)) {
                $days = (int) $now->diffInDays($trialEnds, false);

                return $this->payload(
                    self::STATUS_TRIAL,
                    true,
                    true,
                    $plan ?: $this->trialPlanSlug(),
                    $trialEnds->toIso8601String(),
                    $subscription->grace_ends_at?->timezone($this->timezone())->toIso8601String(),
                    null,
                    max(0, $days),
                    'Trial is active.',
                    'subscription_trial'
                );
            }

            $graceEnds = $subscription->grace_ends_at
                ? Carbon::parse($subscription->grace_ends_at, $this->timezone())
                : ($trialEnds ? $trialEnds->copy()->addDays($this->graceDays()) : $now->copy()->subDay());

            if ($graceEnds->greaterThanOrEqualTo($now)) {
                $days = (int) $now->diffInDays($graceEnds, false);

                return $this->payload(
                    self::STATUS_GRACE,
                    true,
                    true,
                    $plan ?: $this->trialPlanSlug(),
                    $trialEnds?->toIso8601String(),
                    $graceEnds->toIso8601String(),
                    null,
                    max(0, $days),
                    'Trial ended. You are in a grace period — choose a paid plan.',
                    'subscription_grace'
                );
            }

            return $this->payload(
                self::STATUS_EXPIRED,
                false,
                false,
                $plan ?: $this->trialPlanSlug(),
                $trialEnds?->toIso8601String(),
                $graceEnds->toIso8601String(),
                null,
                0,
                'Trial and grace period ended. Upgrade to restore write access.',
                'subscription_expired'
            );
        }

        // Pending unpaid checkout / inactive without trial dates → expired (read-only).
        return $this->payload(
            self::STATUS_EXPIRED,
            false,
            false,
            $plan,
            $subscription->trial_ends_at?->timezone($this->timezone())->toIso8601String(),
            $subscription->grace_ends_at?->timezone($this->timezone())->toIso8601String(),
            $subscription->ends_at ? Carbon::parse($subscription->ends_at, $this->timezone())->toIso8601String() : null,
            0,
            'Subscription is not active. Upgrade to restore write access.',
            'subscription_expired'
        );
    }

    public function organizationForUser(?User $user): ?Organization
    {
        if (! $user) {
            return null;
        }

        if ($user->effectiveAppRole() === 'admin') {
            return null;
        }

        $membership = $user->primaryOrganizationMembership();
        $organization = $membership?->organization;
        if ($organization) {
            return $organization;
        }

        return Organization::query()->where('owner_user_id', $user->id)->first();
    }

    /**
     * Start a trial for a brand-new organization subscription.
     */
    public function startTrial(Subscription $subscription, ?Organization $organization = null, ?User $actor = null): Subscription
    {
        $now = $this->now();
        $trialEnds = $now->copy()->addDays($this->trialDays());
        $graceEnds = $trialEnds->copy()->addDays($this->graceDays());
        $fromPlan = $subscription->plan;
        $fromStatus = $subscription->status;

        $subscription->forceFill([
            'plan' => $this->trialPlanSlug(),
            'status' => 'trialing',
            'trial_started_at' => $now,
            'trial_ends_at' => $trialEnds,
            'grace_ends_at' => $graceEnds,
            'starts_at' => $now->toDateString(),
            'ends_at' => null,
        ])->save();

        $this->audit(
            $organization,
            $subscription,
            $actor,
            'trial_started',
            $fromPlan,
            $this->trialPlanSlug(),
            $fromStatus,
            'trialing',
            'Organization created — trial started.',
            ['trial_days' => $this->trialDays(), 'grace_days' => $this->graceDays()]
        );

        return $subscription;
    }

    public function activatePaid(
        Subscription $subscription,
        string $planSlug,
        ?Organization $organization,
        ?User $actor,
        string $billingCycle = 'monthly',
        ?Carbon $periodEnd = null,
        ?string $reason = null,
    ): Subscription {
        $now = $this->now();
        $fromPlan = $subscription->plan;
        $fromStatus = $subscription->status;
        $ends = $periodEnd ?? $now->copy()->addMonth();

        $subscription->forceFill([
            'plan' => strtolower($planSlug),
            'status' => 'active',
            'billing_cycle' => $billingCycle,
            'starts_at' => $now->toDateString(),
            'ends_at' => $ends->toDateString(),
            'grace_ends_at' => $ends->copy()->addDays($this->graceDays()),
            'access_note' => $reason,
        ])->save();

        $this->audit(
            $organization,
            $subscription,
            $actor,
            'plan_activated',
            $fromPlan,
            $planSlug,
            $fromStatus,
            'active',
            $reason ?? 'Paid plan activated.',
            ['ends_at' => $ends->toDateString()]
        );

        return $subscription;
    }

    public function extendTrial(
        Subscription $subscription,
        int $extraDays,
        ?Organization $organization,
        ?User $actor,
        string $reason,
    ): Subscription {
        $now = $this->now();
        $base = $subscription->trial_ends_at
            ? Carbon::parse($subscription->trial_ends_at, $this->timezone())
            : $now;
        if ($base->lessThan($now)) {
            $base = $now->copy();
        }
        $trialEnds = $base->copy()->addDays(max(1, $extraDays));
        $graceEnds = $trialEnds->copy()->addDays($this->graceDays());
        $fromStatus = $subscription->status;

        $subscription->forceFill([
            'status' => 'trialing',
            'plan' => $subscription->plan ?: $this->trialPlanSlug(),
            'trial_ends_at' => $trialEnds,
            'grace_ends_at' => $graceEnds,
            'access_note' => $reason,
        ])->save();

        $this->audit(
            $organization,
            $subscription,
            $actor,
            'trial_extended',
            $subscription->plan,
            $subscription->plan,
            $fromStatus,
            'trialing',
            $reason,
            ['extra_days' => $extraDays, 'trial_ends_at' => $trialEnds->toIso8601String()]
        );

        return $subscription;
    }

    public function forceExpired(Subscription $subscription, ?Organization $organization, ?User $actor, string $reason): Subscription
    {
        $now = $this->now();
        $fromStatus = $subscription->status;
        $subscription->forceFill([
            'status' => 'trialing',
            'trial_ends_at' => $now->copy()->subDays(2),
            'grace_ends_at' => $now->copy()->subDay(),
            'ends_at' => $now->copy()->subDays(2)->toDateString(),
            'access_note' => $reason,
        ])->save();

        $this->audit($organization, $subscription, $actor, 'forced_expired', $subscription->plan, $subscription->plan, $fromStatus, 'expired', $reason, []);

        return $subscription;
    }

    public function forceGrace(Subscription $subscription, ?Organization $organization, ?User $actor, string $reason): Subscription
    {
        $now = $this->now();
        $fromStatus = $subscription->status;
        $subscription->forceFill([
            'status' => 'trialing',
            'trial_ends_at' => $now->copy()->subDay(),
            'grace_ends_at' => $now->copy()->addDays(max(1, $this->graceDays())),
            'access_note' => $reason,
        ])->save();

        $this->audit($organization, $subscription, $actor, 'forced_grace', $subscription->plan, $subscription->plan, $fromStatus, 'grace', $reason, []);

        return $subscription;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function audit(
        ?Organization $organization,
        ?Subscription $subscription,
        ?User $actor,
        string $event,
        ?string $fromPlan,
        ?string $toPlan,
        ?string $fromStatus,
        ?string $toStatus,
        ?string $reason,
        array $meta = [],
    ): void {
        SubscriptionAuditLog::query()->create([
            'organization_id' => $organization?->id,
            'subscription_id' => $subscription?->id,
            'actor_user_id' => $actor?->id,
            'event' => $event,
            'from_plan' => $fromPlan,
            'to_plan' => $toPlan,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'reason' => $reason,
            'meta' => $meta,
            'created_at' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(
        string $status,
        bool $canWrite,
        bool $canReviewDtr,
        ?string $plan,
        ?string $trialEndsAt,
        ?string $graceEndsAt,
        ?string $paidEndsAt,
        ?int $daysRemaining,
        string $message,
        string $code,
    ): array {
        return [
            'status' => $status,
            'canWrite' => $canWrite,
            'canReviewDtr' => $canReviewDtr,
            'plan' => $plan,
            'trialEndsAt' => $trialEndsAt,
            'graceEndsAt' => $graceEndsAt,
            'paidEndsAt' => $paidEndsAt,
            'daysRemaining' => $daysRemaining,
            'message' => $message,
            'code' => $code,
            'trialDays' => $this->trialDays(),
            'graceDays' => $this->graceDays(),
        ];
    }
}
