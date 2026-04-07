<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionPlanDefinition;
use App\Models\Organization;
use App\Models\User;
use App\Services\SubscriptionPlanService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SubscriptionPlanController extends Controller
{
    public function __construct(private readonly SubscriptionPlanService $plans)
    {
    }

    public function index()
    {
        $plans = $this->plans->getAllPlans();

        if ($plans->isEmpty()) {
            return response()->json([
                'data' => $this->plans->fallbackSerializedPlans(),
            ]);
        }

        return response()->json([
            'data' => $plans
                ->map(fn (SubscriptionPlanDefinition $plan) => $this->plans->serializePlan($plan))
                ->values(),
        ]);
    }

    public function update(Request $request, string $planSlug)
    {
        /** @var User $user */
        $user = $request->user();
        if (! $user->isPlatformAdmin()) {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $plan = SubscriptionPlanDefinition::query()
            ->where('slug', $planSlug)
            ->where('is_active', true)
            ->first();

        if (! $plan) {
            return response()->json(['message' => 'Subscription plan not found.'], Response::HTTP_NOT_FOUND);
        }

        $data = $request->validate([
            'description' => ['required', 'string'],
            'schoolPrice' => ['required', 'integer', 'min:0'],
            'companyPrice' => ['required', 'integer', 'min:0'],
            'schoolFeatures' => ['required', 'array', 'min:1'],
            'schoolFeatures.*' => ['required', 'string', 'max:255'],
            'companyFeatures' => ['required', 'array', 'min:1'],
            'companyFeatures.*' => ['required', 'string', 'max:255'],
            'limits' => ['required', 'array'],
            'limits.school' => ['required', 'array'],
            'limits.school.coordinators' => ['required', 'integer', 'min:0'],
            'limits.school.students' => ['required', 'integer', 'min:0'],
            'limits.company' => ['required', 'array'],
            'limits.company.accounts' => ['required', 'integer', 'min:0'],
            'limits.company.internships' => ['required', 'integer', 'min:0'],
        ]);

        $plan->fill([
            'description' => trim($data['description']),
            'school_price' => $data['schoolPrice'],
            'company_price' => $data['companyPrice'],
            'school_features' => array_values(array_filter(array_map('trim', $data['schoolFeatures']))),
            'company_features' => array_values(array_filter(array_map('trim', $data['companyFeatures']))),
            'school_coordinators_limit' => $data['limits']['school']['coordinators'],
            'school_students_limit' => $data['limits']['school']['students'],
            'company_accounts_limit' => $data['limits']['company']['accounts'],
            'company_internships_limit' => $data['limits']['company']['internships'],
        ]);
        $plan->save();

        Organization::query()
            ->with(['subscription', 'owner'])
            ->whereHas('subscription', fn ($query) => $query->where('plan', $plan->slug))
            ->orWhere(function ($query) {
                $query->whereHas('subscription', fn ($subQuery) => $subQuery->where('status', 'inactive'));
            })
            ->get()
            ->each(fn (Organization $organization) => $this->plans->syncOrganizationCompliance($organization));

        return response()->json([
            'data' => $this->plans->serializePlan($plan->fresh()),
        ]);
    }
}
