<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Subscription;
use App\Models\SubscriptionAuditLog;
use App\Models\SubscriptionPlanDefinition;
use App\Models\User;
use App\Services\SubscriptionAccessService;
use App\Services\SubscriptionPlanService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class AdminSubscriptionController extends Controller
{
    public function __construct(
        private readonly SubscriptionAccessService $access,
        private readonly SubscriptionPlanService $plans,
    ) {
    }

    public function index(Request $request)
    {
        if (! $this->isAdmin($request->user())) {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $statusFilter = strtolower(trim((string) $request->query('status', '')));

        $rows = Organization::query()
            ->with('subscription')
            ->whereIn('type', ['school', 'company', 'municipal_hall'])
            ->orderBy('name')
            ->get()
            ->map(function (Organization $organization) {
                $state = $this->access->resolve($organization);

                return [
                    'id' => (string) $organization->id,
                    'name' => $organization->name,
                    'type' => $organization->type,
                    'subscriptionId' => $organization->subscription_id ? (string) $organization->subscription_id : null,
                    'rawPlan' => $organization->subscription?->plan,
                    'rawStatus' => $organization->subscription?->status,
                    'access' => $state,
                ];
            })
            ->when($statusFilter !== '', fn ($collection) => $collection->filter(
                fn (array $row) => ($row['access']['status'] ?? '') === $statusFilter
            )->values());

        return response()->json(['data' => $rows->values()]);
    }

    public function show(Request $request, Organization $organization)
    {
        if (! $this->isAdmin($request->user())) {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $organization->load('subscription');
        $audit = SubscriptionAuditLog::query()
            ->where('organization_id', $organization->id)
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (SubscriptionAuditLog $log) => [
                'id' => (string) $log->id,
                'event' => $log->event,
                'fromPlan' => $log->from_plan,
                'toPlan' => $log->to_plan,
                'fromStatus' => $log->from_status,
                'toStatus' => $log->to_status,
                'reason' => $log->reason,
                'actorUserId' => $log->actor_user_id ? (string) $log->actor_user_id : null,
                'meta' => $log->meta,
                'createdAt' => $log->created_at?->toIso8601String(),
            ]);

        return response()->json([
            'data' => [
                'id' => (string) $organization->id,
                'name' => $organization->name,
                'type' => $organization->type,
                'access' => $this->access->resolve($organization),
                'subscription' => $organization->subscription ? [
                    'id' => (string) $organization->subscription->id,
                    'plan' => $organization->subscription->plan,
                    'status' => $organization->subscription->status,
                    'billingCycle' => $organization->subscription->billing_cycle,
                    'startsAt' => $organization->subscription->starts_at?->toDateString(),
                    'endsAt' => $organization->subscription->ends_at?->toDateString(),
                    'trialStartedAt' => $organization->subscription->trial_started_at?->toIso8601String(),
                    'trialEndsAt' => $organization->subscription->trial_ends_at?->toIso8601String(),
                    'graceEndsAt' => $organization->subscription->grace_ends_at?->toIso8601String(),
                    'accessNote' => $organization->subscription->access_note,
                ] : null,
                'audit' => $audit,
            ],
        ]);
    }

    public function extendTrial(Request $request, Organization $organization)
    {
        if (! $this->isAdmin($request->user())) {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $data = $request->validate([
            'extraDays' => ['required', 'integer', 'min:1', 'max:365'],
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        $subscription = $this->ensureSubscription($organization);
        $this->access->extendTrial(
            $subscription,
            (int) $data['extraDays'],
            $organization,
            $request->user(),
            $data['reason']
        );
        $this->plans->syncOrganizationCompliance($organization->fresh(['subscription', 'owner']));

        return response()->json([
            'data' => [
                'access' => $this->access->resolve($organization->fresh('subscription')),
            ],
        ]);
    }

    public function activate(Request $request, Organization $organization)
    {
        if (! $this->isAdmin($request->user())) {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $data = $request->validate([
            'plan' => ['required', 'string', Rule::in(['standard', 'premium'])],
            'billingCycle' => ['nullable', 'string', 'max:32'],
            'periodEndsAt' => ['nullable', 'date'],
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        $planExists = SubscriptionPlanDefinition::query()
            ->where('slug', $data['plan'])
            ->where('is_active', true)
            ->exists();
        if (! $planExists) {
            return response()->json(['message' => 'Plan not found.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $subscription = $this->ensureSubscription($organization);
        $periodEnd = ! empty($data['periodEndsAt'])
            ? Carbon::parse($data['periodEndsAt'], $this->access->timezone())->endOfDay()
            : null;

        $this->access->activatePaid(
            $subscription,
            $data['plan'],
            $organization,
            $request->user(),
            strtolower((string) ($data['billingCycle'] ?? 'monthly')) ?: 'monthly',
            $periodEnd,
            'Admin manual activation: '.$data['reason']
        );
        $this->plans->syncOrganizationCompliance($organization->fresh(['subscription', 'owner']));

        return response()->json([
            'data' => [
                'access' => $this->access->resolve($organization->fresh('subscription')),
            ],
        ]);
    }

    public function deactivate(Request $request, Organization $organization)
    {
        if (! $this->isAdmin($request->user())) {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
            'mode' => ['nullable', Rule::in(['expired', 'grace'])],
        ]);

        $subscription = $this->ensureSubscription($organization);
        $mode = $data['mode'] ?? 'expired';
        if ($mode === 'grace') {
            $this->access->forceGrace($subscription, $organization, $request->user(), 'Admin forced grace: '.$data['reason']);
        } else {
            $this->access->forceExpired($subscription, $organization, $request->user(), 'Admin forced expired: '.$data['reason']);
        }
        $this->plans->syncOrganizationCompliance($organization->fresh(['subscription', 'owner']));

        return response()->json([
            'data' => [
                'access' => $this->access->resolve($organization->fresh('subscription')),
            ],
        ]);
    }

    private function ensureSubscription(Organization $organization): Subscription
    {
        if ($organization->subscription) {
            return $organization->subscription;
        }

        $subscription = Subscription::query()->create([
            'plan' => $this->access->trialPlanSlug(),
            'status' => 'trialing',
            'billing_cycle' => 'monthly',
        ]);
        $organization->subscription_id = $subscription->id;
        $organization->save();

        return $subscription;
    }

    private function isAdmin(?User $user): bool
    {
        return (bool) $user?->isPlatformAdmin();
    }
}
