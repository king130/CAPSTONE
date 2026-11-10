<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Services\SubscriptionAccessService;
use Illuminate\Console\Command;

/**
 * Read-only audit: compare previous trial/grace/expired heuristics vs the shared resolve().
 * Does not modify any rows.
 */
class AuditSubscriptionAccessStatusesCommand extends Command
{
    protected $signature = 'subscription:audit-access-statuses
        {--json : Emit JSON instead of a table}';

    protected $description = 'List organizations whose resolved subscription status differs from the legacy trial/grace/expired heuristic (read-only).';

    public function handle(SubscriptionAccessService $access): int
    {
        $changed = [];
        $summary = [
            'total' => 0,
            'unchanged' => 0,
            'changed' => 0,
            'by_new_status' => [],
        ];

        Organization::query()
            ->with('subscription')
            ->whereIn('type', ['school', 'company', 'municipal_hall'])
            ->orderBy('id')
            ->chunkById(100, function ($organizations) use ($access, &$changed, &$summary) {
                foreach ($organizations as $organization) {
                    $summary['total']++;
                    $resolved = $access->resolve($organization);
                    $legacy = $this->legacyHeuristic($organization, $access);
                    $newStatus = (string) ($resolved['status'] ?? '');
                    $summary['by_new_status'][$newStatus] = ($summary['by_new_status'][$newStatus] ?? 0) + 1;

                    if ($legacy === $newStatus) {
                        $summary['unchanged']++;

                        continue;
                    }

                    $summary['changed']++;
                    $changed[] = [
                        'organization_id' => $organization->id,
                        'name' => $organization->name,
                        'type' => $organization->type,
                        'verification' => $resolved['verificationStatus'] ?? null,
                        'subscription_plan' => $organization->subscription?->plan,
                        'subscription_status' => $organization->subscription?->status,
                        'ends_at' => $organization->subscription?->ends_at,
                        'trial_ends_at' => $organization->subscription?->trial_ends_at,
                        'grace_ends_at' => $organization->subscription?->grace_ends_at,
                        'legacy_status' => $legacy,
                        'new_status' => $newStatus,
                        'can_write' => (bool) ($resolved['canWrite'] ?? false),
                    ];
                }
            });

        if ($this->option('json')) {
            $this->line(json_encode([
                'summary' => $summary,
                'changed' => $changed,
            ], JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Audited %d organizations — %d would change status, %d unchanged.',
            $summary['total'],
            $summary['changed'],
            $summary['unchanged']
        ));
        $this->line('New status counts: '.json_encode($summary['by_new_status']));

        if ($changed === []) {
            $this->info('No organizations would change under the new rule.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Name', 'Type', 'Verification', 'Plan', 'Sub status', 'Legacy', 'New', 'canWrite'],
            array_map(static fn (array $row) => [
                $row['organization_id'],
                mb_substr((string) $row['name'], 0, 28),
                $row['type'],
                $row['verification'] ?? '—',
                $row['subscription_plan'] ?? '—',
                $row['subscription_status'] ?? '—',
                $row['legacy_status'],
                $row['new_status'],
                $row['can_write'] ? 'yes' : 'no',
            ], $changed)
        );

        return self::SUCCESS;
    }

    /**
     * Approximate pre-change resolve() outcomes for comparison (trial|active|grace|expired),
     * then map those labels so differences vs unverified/free/past_due/pending_payment are visible.
     */
    private function legacyHeuristic(Organization $organization, SubscriptionAccessService $access): string
    {
        $verification = $access->verificationStatusFor($organization);
        // Old code did not short-circuit on verification — pending orgs could still be "expired".
        $subscription = $organization->subscription;
        if (! $subscription) {
            return 'expired';
        }

        $now = $access->now();
        $plan = strtolower(trim((string) $subscription->plan)) ?: null;
        $rawStatus = strtolower(trim((string) $subscription->status));
        $settings = is_array($organization->settings) ? $organization->settings : [];
        $pending = is_array($settings['pending_plan_change'] ?? null) ? $settings['pending_plan_change'] : null;

        if ($plan === 'free' && in_array($rawStatus, ['active', 'trialing', 'trial'], true) && ! $pending) {
            return 'active';
        }

        if ($rawStatus === 'active' && $plan && $plan !== 'free') {
            $ends = $subscription->ends_at ? \Carbon\Carbon::parse($subscription->ends_at, $access->timezone())->endOfDay() : null;
            if ($ends === null || $ends->greaterThanOrEqualTo($now)) {
                return 'active';
            }
        }

        if ($subscription->trial_ends_at) {
            $trialEnds = \Carbon\Carbon::parse($subscription->trial_ends_at, $access->timezone());
            if ($trialEnds->greaterThanOrEqualTo($now) && in_array($rawStatus, ['trialing', 'trial', 'active'], true)) {
                return 'trial';
            }
        }

        $graceEnds = $subscription->grace_ends_at
            ? \Carbon\Carbon::parse($subscription->grace_ends_at, $access->timezone())
            : null;
        if ($graceEnds && $graceEnds->greaterThanOrEqualTo($now)) {
            return 'grace';
        }

        if ($pending || $rawStatus === 'pending') {
            return 'expired';
        }

        if (in_array($rawStatus, ['inactive', 'canceled', 'cancelled', 'expired'], true)) {
            return 'expired';
        }

        // Fallback used by the old service.
        return 'expired';
    }
}
