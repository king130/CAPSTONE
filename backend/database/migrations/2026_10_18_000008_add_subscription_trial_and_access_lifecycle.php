<?php

use App\Models\Organization;
use App\Models\Subscription;
use App\Models\SubscriptionPlanDefinition;
use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plan_definitions', function (Blueprint $table) {
            if (! Schema::hasColumn('subscription_plan_definitions', 'is_selectable')) {
                $table->boolean('is_selectable')->default(true)->after('is_active');
            }
            if (! Schema::hasColumn('subscription_plan_definitions', 'is_legacy')) {
                $table->boolean('is_legacy')->default(false)->after('is_selectable');
            }
        });

        // Free plan stays in DB but is not selectable for new orgs.
        DB::table('subscription_plan_definitions')
            ->where('slug', 'free')
            ->update([
                'is_selectable' => false,
                'is_legacy' => true,
            ]);

        Schema::table('subscriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('subscriptions', 'trial_started_at')) {
                $table->timestamp('trial_started_at')->nullable()->after('ends_at');
            }
            if (! Schema::hasColumn('subscriptions', 'trial_ends_at')) {
                $table->timestamp('trial_ends_at')->nullable()->after('trial_started_at');
            }
            if (! Schema::hasColumn('subscriptions', 'grace_ends_at')) {
                $table->timestamp('grace_ends_at')->nullable()->after('trial_ends_at');
            }
            if (! Schema::hasColumn('subscriptions', 'access_note')) {
                $table->text('access_note')->nullable()->after('grace_ends_at');
            }
        });

        if (! Schema::hasTable('subscription_audit_logs')) {
            Schema::create('subscription_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
                $table->foreignId('subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete();
                $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('event', 64);
                $table->string('from_plan', 64)->nullable();
                $table->string('to_plan', 64)->nullable();
                $table->string('from_status', 64)->nullable();
                $table->string('to_status', 64)->nullable();
                $table->text('reason')->nullable();
                $table->json('meta')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }

        $tz = config('subscription.timezone', 'Asia/Manila');
        $trialDays = (int) config('subscription.trial_days', 14);
        $graceDays = (int) config('subscription.grace_days', 7);
        $trialPlan = (string) config('subscription.trial_plan', 'standard');
        $now = Carbon::now($tz);
        $trialEnds = $now->copy()->addDays($trialDays);
        $graceEnds = $trialEnds->copy()->addDays($graceDays);

        // Existing free / inactive orgs get a fresh trial — do not lock anyone out silently.
        $affected = [];
        Organization::query()
            ->with('subscription')
            ->orderBy('id')
            ->each(function (Organization $organization) use (&$affected, $now, $trialEnds, $graceEnds, $trialPlan) {
                $subscription = $organization->subscription;
                if (! $subscription) {
                    return;
                }

                $plan = strtolower(trim((string) $subscription->plan));
                $status = strtolower(trim((string) $subscription->status));
                $isPaidActive = $status === 'active' && ! in_array($plan, ['free', ''], true);

                if ($isPaidActive) {
                    return;
                }

                $fromPlan = $subscription->plan;
                $fromStatus = $subscription->status;

                $subscription->forceFill([
                    'plan' => $trialPlan,
                    'status' => 'trialing',
                    'trial_started_at' => $now,
                    'trial_ends_at' => $trialEnds,
                    'grace_ends_at' => $graceEnds,
                    'starts_at' => $now->toDateString(),
                    'ends_at' => null,
                    'access_note' => 'Phase H migration: legacy free/pending org granted a fresh trial.',
                ])->save();

                DB::table('subscription_audit_logs')->insert([
                    'organization_id' => $organization->id,
                    'subscription_id' => $subscription->id,
                    'actor_user_id' => null,
                    'event' => 'migration_trial_granted',
                    'from_plan' => $fromPlan,
                    'to_plan' => $trialPlan,
                    'from_status' => $fromStatus,
                    'to_status' => 'trialing',
                    'reason' => 'Existing free/pending organization granted a fresh trial at Phase H migration.',
                    'meta' => json_encode(['organization_type' => $organization->type, 'organization_name' => $organization->name]),
                    'created_at' => now(),
                ]);

                $affected[] = [
                    'type' => $organization->type,
                    'id' => $organization->id,
                    'name' => $organization->name,
                ];
            });

        // Store migration report for the operator (local sqlite only).
        if ($affected !== []) {
            DB::table('subscription_audit_logs')->insert([
                'organization_id' => null,
                'subscription_id' => null,
                'actor_user_id' => null,
                'event' => 'migration_trial_summary',
                'from_plan' => null,
                'to_plan' => $trialPlan,
                'from_status' => null,
                'to_status' => 'trialing',
                'reason' => 'Phase H migration granted trials to '.count($affected).' organizations.',
                'meta' => json_encode(['organizations' => $affected]),
                'created_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_audit_logs');

        Schema::table('subscriptions', function (Blueprint $table) {
            foreach (['access_note', 'grace_ends_at', 'trial_ends_at', 'trial_started_at'] as $column) {
                if (Schema::hasColumn('subscriptions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('subscription_plan_definitions', function (Blueprint $table) {
            foreach (['is_legacy', 'is_selectable'] as $column) {
                if (Schema::hasColumn('subscription_plan_definitions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
