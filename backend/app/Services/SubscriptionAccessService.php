<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Organization;
use App\Models\School;
use App\Models\Subscription;
use App\Models\SubscriptionAuditLog;
use App\Models\User;
use Carbon\Carbon;

/**
 * Single source of truth for organization subscription access.
 *
 * Statuses (exactly one):
 * - unverified: awaiting / rejected verification (never "expired")
 * - trial: timed trial window after verification (writable, trial_plan limits)
 * - free: free plan with no active trial window
 * - active: paid plan in period
 * - pending_payment: checkout started, not completed
 * - past_due: paid or trial period ended, still inside grace
 * - expired: trial/paid period ended and grace finished
 */
class SubscriptionAccessService
{
    public const STATUS_UNVERIFIED = 'unverified';

    public const STATUS_TRIAL = 'trial';

    public const STATUS_FREE = 'free';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PENDING_PAYMENT = 'pending_payment';

    public const STATUS_PAST_DUE = 'past_due';

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
        return max(1, (int) config('subscription.trial_days', 30));
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
     *   code: string,
     *   verificationStatus: string|null,
     *   trialDays: int,
     *   graceDays: int
     * }
     */
    public function resolve(?Organization $organization): array
    {
        if (! $organization) {
            return $this->payload(
                self::STATUS_EXPIRED,
                false,
                false,
                null,
                null,
                null,
                null,
                null,
                'No organization context.',
                'subscription_missing',
                null
            );
        }

        $organization->loadMissing('subscription');
        $verificationStatus = $this->verificationStatusFor($organization);

        // Unverified orgs are never "expired" — free limits while uploading docs; trial clock still runs.
        if (! $this->isVerificationApproved($verificationStatus)) {
            $subscription = $organization->subscription;
            $trialEndsAt = $subscription?->trial_ends_at;
            $days = null;
            if ($trialEndsAt) {
                $days = max(0, (int) $this->now()->diffInDays(
                    Carbon::parse($trialEndsAt, $this->timezone()),
                    false
                ));
            }

            return $this->payload(
                self::STATUS_UNVERIFIED,
                true,
                true,
                'free',
                $trialEndsAt?->timezone($this->timezone())->toIso8601String(),
                $subscription?->grace_ends_at?->timezone($this->timezone())->toIso8601String(),
                null,
                $days,
                'Organization verification is still pending. Free-plan limits apply until approved.',
                'subscription_unverified',
                $verificationStatus
            );
        }

        $subscription = $organization->subscription;
        if (! $subscription) {
            return $this->payload(
                self::STATUS_FREE,
                true,
                true,
                'free',
                null,
                null,
                null,
                null,
                'No subscription row — treating as free plan.',
                'subscription_free',
                $verificationStatus
            );
        }

        $now = $this->now();
        $plan = strtolower(trim((string) $subscription->plan)) ?: null;
        $rawStatus = strtolower(trim((string) $subscription->status));
        $settings = is_array($organization->settings) ? $organization->settings : [];
        $pendingChange = is_array($settings['pending_plan_change'] ?? null) ? $settings['pending_plan_change'] : null;
        $trialPlan = $this->trialPlanSlug();
        $trialEnds = $subscription->trial_ends_at
            ? Carbon::parse($subscription->trial_ends_at, $this->timezone())
            : null;

        // Checkout in flight — never treat as expired.
        if ($pendingChange || $rawStatus === 'pending') {
            return $this->payload(
                self::STATUS_PENDING_PAYMENT,
                true,
                true,
                $plan && $plan !== 'free' ? $plan : 'free',
                $trialEnds?->toIso8601String(),
                $subscription->grace_ends_at?->timezone($this->timezone())->toIso8601String(),
                $subscription->ends_at ? Carbon::parse($subscription->ends_at, $this->timezone())->toIso8601String() : null,
                null,
                'Payment is pending. Complete checkout to activate your plan.',
                'subscription_pending_payment',
                $verificationStatus
            );
        }

        $paidEnds = $subscription->ends_at
            ? Carbon::parse($subscription->ends_at, $this->timezone())->endOfDay()
            : null;
        $hadPaidPlan = $this->hadPaidPlan($subscription, $plan, $rawStatus, $paidEnds);

        // Active paid plan still in period (or open-ended paid with no trial window).
        if ($hadPaidPlan && $rawStatus === 'active' && ($paidEnds === null || $paidEnds->greaterThanOrEqualTo($now))) {
            $days = $paidEnds ? (int) $now->diffInDays($paidEnds, false) : null;

            return $this->payload(
                self::STATUS_ACTIVE,
                true,
                true,
                $plan,
                $trialEnds?->toIso8601String(),
                $subscription->grace_ends_at?->timezone($this->timezone())->toIso8601String(),
                $paidEnds?->toIso8601String(),
                $days,
                'Paid plan is active.',
                'subscription_active',
                $verificationStatus
            );
        }

        // Previously paid — period ended.
        if ($hadPaidPlan && $paidEnds !== null && $paidEnds->lessThan($now)) {
            $graceEnds = $subscription->grace_ends_at
                ? Carbon::parse($subscription->grace_ends_at, $this->timezone())
                : $paidEnds->copy()->addDays($this->graceDays());

            if ($graceEnds->greaterThanOrEqualTo($now)) {
                $days = (int) $now->diffInDays($graceEnds, false);

                return $this->payload(
                    self::STATUS_PAST_DUE,
                    true,
                    true,
                    $plan,
                    null,
                    $graceEnds->toIso8601String(),
                    $paidEnds->toIso8601String(),
                    max(0, $days),
                    'Payment failed or the paid period ended. Update billing to avoid losing write access.',
                    'subscription_past_due',
                    $verificationStatus
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
                'subscription_expired',
                $verificationStatus
            );
        }

        // Timed trial window (registration startTrial) — before free fallback.
        if ($trialEnds !== null && $trialEnds->greaterThanOrEqualTo($now)) {
            $days = (int) $now->diffInDays($trialEnds, false);

            return $this->payload(
                self::STATUS_TRIAL,
                true,
                true,
                $trialPlan,
                $trialEnds->toIso8601String(),
                $subscription->grace_ends_at?->timezone($this->timezone())->toIso8601String(),
                null,
                max(0, $days),
                'Trial is active.',
                'subscription_trial',
                $verificationStatus
            );
        }

        // Trial ended (never paid) — grace then expired.
        if ($trialEnds !== null && $trialEnds->lessThan($now)) {
            $graceEnds = $subscription->grace_ends_at
                ? Carbon::parse($subscription->grace_ends_at, $this->timezone())
                : $trialEnds->copy()->addDays($this->graceDays());

            if ($graceEnds->greaterThanOrEqualTo($now)) {
                $days = (int) $now->diffInDays($graceEnds, false);

                return $this->payload(
                    self::STATUS_PAST_DUE,
                    true,
                    true,
                    $trialPlan,
                    $trialEnds->toIso8601String(),
                    $graceEnds->toIso8601String(),
                    null,
                    max(0, $days),
                    'Your trial ended. Choose a paid plan to keep write access after the grace period.',
                    'subscription_past_due',
                    $verificationStatus
                );
            }

            return $this->payload(
                self::STATUS_EXPIRED,
                false,
                false,
                $trialPlan,
                $trialEnds->toIso8601String(),
                $graceEnds->toIso8601String(),
                null,
                0,
                'Subscription expired. Upgrade to restore write access.',
                'subscription_expired',
                $verificationStatus
            );
        }

        // Free plan with no trial window.
        return $this->payload(
            self::STATUS_FREE,
            true,
            true,
            'free',
            null,
            $subscription->grace_ends_at?->timezone($this->timezone())->toIso8601String(),
            null,
            null,
            'Free plan is active.',
            'subscription_free',
            $verificationStatus
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

    public function verificationStatusFor(Organization $organization): ?string
    {
        if ($organization->type === 'school') {
            $status = School::query()->where('organization_id', $organization->id)->value('verification_status');

            return $status !== null ? strtolower(trim((string) $status)) : 'pending';
        }

        if (in_array($organization->type, ['company', 'municipal_hall'], true)) {
            $status = Company::query()->where('organization_id', $organization->id)->value('verification_status');

            return $status !== null ? strtolower(trim((string) $status)) : 'pending';
        }

        return null;
    }

    public function isVerificationApproved(?string $status): bool
    {
        return strtolower(trim((string) $status)) === 'approved';
    }

    /**
     * Start a timed trial for a brand-new organization (clock starts at registration).
     */
    public function startTrial(Subscription $subscription, ?Organization $organization = null, ?User $actor = null): Subscription
    {
        $now = $this->now();
        $fromPlan = $subscription->plan;
        $fromStatus = $subscription->status;
        $trialPlan = $this->trialPlanSlug();
        $trialEnds = $now->copy()->addDays($this->trialDays());
        $graceEnds = $trialEnds->copy()->addDays($this->graceDays());

        $subscription->forceFill([
            'plan' => $trialPlan,
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
            $trialPlan,
            $fromStatus,
            'trialing',
            'Organization created — timed trial started.',
            [
                'trial_days' => $this->trialDays(),
                'trial_ends_at' => $trialEnds->toIso8601String(),
            ]
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
        // Extending "trial" keeps the org on free writable access; optionally bump a soft note window.
        $now = $this->now();
        $fromStatus = $subscription->status;
        $noteEnds = $now->copy()->addDays(max(1, $extraDays));

        $trialPlan = $this->trialPlanSlug();
        $graceEnds = $noteEnds->copy()->addDays($this->graceDays());

        $subscription->forceFill([
            'status' => 'trialing',
            'plan' => $trialPlan,
            'trial_ends_at' => $noteEnds,
            'grace_ends_at' => $graceEnds,
            'ends_at' => null,
            'access_note' => $reason,
        ])->save();

        $this->audit(
            $organization,
            $subscription,
            $actor,
            'trial_extended',
            $subscription->plan,
            $trialPlan,
            $fromStatus,
            'trialing',
            $reason,
            ['extra_days' => $extraDays]
        );

        return $subscription;
    }

    public function forceExpired(Subscription $subscription, ?Organization $organization, ?User $actor, string $reason): Subscription
    {
        $now = $this->now();
        $fromStatus = $subscription->status;
        $plan = strtolower(trim((string) $subscription->plan));
        if ($plan === '' || $plan === 'free') {
            $plan = 'standard';
        }

        $subscription->forceFill([
            'plan' => $plan,
            'status' => 'active',
            'ends_at' => $now->copy()->subDays(2)->toDateString(),
            'grace_ends_at' => $now->copy()->subDay(),
            'trial_ends_at' => null,
            'access_note' => $reason,
        ])->save();

        $this->audit($organization, $subscription, $actor, 'forced_expired', $subscription->plan, $plan, $fromStatus, 'expired', $reason, []);

        return $subscription;
    }

    public function forceGrace(Subscription $subscription, ?Organization $organization, ?User $actor, string $reason): Subscription
    {
        $now = $this->now();
        $fromStatus = $subscription->status;
        $plan = strtolower(trim((string) $subscription->plan));
        if ($plan === '' || $plan === 'free') {
            $plan = 'standard';
        }

        $subscription->forceFill([
            'plan' => $plan,
            'status' => 'active',
            'ends_at' => $now->copy()->subDay()->toDateString(),
            'grace_ends_at' => $now->copy()->addDays(max(1, $this->graceDays())),
            'trial_ends_at' => null,
            'access_note' => $reason,
        ])->save();

        $this->audit($organization, $subscription, $actor, 'forced_past_due', $subscription->plan, $plan, $fromStatus, 'past_due', $reason, []);

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

    private function hadPaidPlan(Subscription $subscription, ?string $plan, string $rawStatus, ?Carbon $paidEnds): bool
    {
        if (! $plan || $plan === 'free') {
            return false;
        }

        // Timed trial is not a paid entitlement.
        if (in_array($rawStatus, ['trialing', 'trial'], true)) {
            return false;
        }

        // Non-free + active counts as paid even when ends_at is open-ended/null,
        // but only when there is no open trial window (trial uses trial_plan slug).
        if ($rawStatus === 'active') {
            if ($subscription->trial_ends_at && $paidEnds === null) {
                $trialEnds = Carbon::parse($subscription->trial_ends_at, $this->timezone());
                if ($trialEnds->greaterThanOrEqualTo($this->now())) {
                    return false;
                }
            }

            return true;
        }

        // A concrete paid period end (or prior activation audit) means they had a paid plan.
        if ($paidEnds !== null) {
            return true;
        }

        return SubscriptionAuditLog::query()
            ->where('subscription_id', $subscription->id)
            ->where('event', 'plan_activated')
            ->where('to_plan', '!=', 'free')
            ->exists();
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
        ?string $verificationStatus,
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
            'verificationStatus' => $verificationStatus,
            'trialDays' => $this->trialDays(),
            'graceDays' => $this->graceDays(),
        ];
    }
}
