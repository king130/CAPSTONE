<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Services\SubscriptionAccessService;
use Illuminate\Console\Command;

/**
 * Local/testing only — put an organization into Trial, Grace, or Expired for demos.
 */
class SetOrganizationSubscriptionDemoStatus extends Command
{
    protected $signature = 'subscription:demo-status
        {organization : Organization id}
        {status : trial|grace|expired|active}
        {--days=14 : Days for trial extension or paid period}
        {--reason=Local demo status change : Audit reason}';

    protected $description = 'Local/testing only: set an organization subscription into trial, grace, expired, or active for demos.';

    public function handle(SubscriptionAccessService $access): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('This command is only available when APP_ENV is local or testing.');

            return self::FAILURE;
        }

        $organization = Organization::query()->with('subscription')->find($this->argument('organization'));
        if (! $organization) {
            $this->error('Organization not found.');

            return self::FAILURE;
        }

        $subscription = $organization->subscription;
        if (! $subscription) {
            $this->error('Organization has no subscription row.');

            return self::FAILURE;
        }

        $status = strtolower((string) $this->argument('status'));
        $days = max(1, (int) $this->option('days'));
        $reason = (string) $this->option('reason');
        $admin = \App\Models\User::query()->where('role', 'admin')->orderBy('id')->first();

        match ($status) {
            'trial' => $access->startTrial($subscription, $organization, $admin),
            'grace' => $access->forceGrace($subscription, $organization, $admin, $reason),
            'expired' => $access->forceExpired($subscription, $organization, $admin, $reason),
            'active' => $access->activatePaid($subscription, 'standard', $organization, $admin, 'monthly', $access->now()->copy()->addDays($days), $reason),
            default => null,
        };

        if (! in_array($status, ['trial', 'grace', 'expired', 'active'], true)) {
            $this->error('Status must be one of: trial, grace, expired, active.');

            return self::FAILURE;
        }

        $state = $access->resolve($organization->fresh('subscription'));
        $this->info(sprintf(
            'Organization #%d (%s) access status=%s canWrite=%s daysRemaining=%s',
            $organization->id,
            $organization->name,
            $state['status'],
            $state['canWrite'] ? 'yes' : 'no',
            $state['daysRemaining'] ?? 'n/a'
        ));

        return self::SUCCESS;
    }
}
