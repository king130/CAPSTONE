<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Internship;
use App\Models\Notification;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\School;
use App\Models\Student;
use App\Models\SubscriptionPlanDefinition;

class SubscriptionPlanService
{
    public function getAllPlans()
    {
        return SubscriptionPlanDefinition::query()
            ->where('is_active', true)
            ->orderByRaw("case slug when 'free' then 1 when 'standard' then 2 when 'premium' then 3 else 99 end")
            ->get();
    }

    public function getPlanForOrganization(Organization $organization): ?SubscriptionPlanDefinition
    {
        $organization->loadMissing('subscription');

        $planSlug = strtolower((string) ($organization->subscription?->plan ?: 'free'));
        $status = strtolower((string) ($organization->subscription?->status ?: 'inactive'));

        if ($status === 'inactive') {
            $planSlug = 'free';
        }

        return SubscriptionPlanDefinition::query()
            ->where('is_active', true)
            ->where('slug', $planSlug)
            ->first()
            ?? SubscriptionPlanDefinition::query()->where('slug', 'free')->first();
    }

    /**
     * @return array<string, mixed>
     */
    public function serializePlan(SubscriptionPlanDefinition $plan): array
    {
        return [
            'id' => $plan->slug,
            'name' => $plan->name,
            'description' => $plan->description,
            'schoolPrice' => (int) $plan->school_price,
            'companyPrice' => (int) $plan->company_price,
            'features' => [
                'school' => array_values($plan->school_features ?? []),
                'company' => array_values($plan->company_features ?? []),
            ],
            'limits' => [
                'school' => [
                    'coordinators' => (int) $plan->school_coordinators_limit,
                    'students' => (int) $plan->school_students_limit,
                ],
                'company' => [
                    'accounts' => (int) $plan->company_accounts_limit,
                    'internships' => (int) $plan->company_internships_limit,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function usageSummary(Organization $organization): array
    {
        $organization->loadMissing('subscription');

        $school = School::query()->where('organization_id', $organization->id)->first();
        $company = Company::query()->where('organization_id', $organization->id)->first();

        $activeMemberCount = OrganizationMembership::query()
            ->where('organization_id', $organization->id)
            ->where('status', 'active')
            ->whereHas('user', fn ($query) => $query->where('is_active', true)->where('role', $organization->type))
            ->count();

        $studentQuery = Student::query()->where('organization_id', $organization->id);
        if ($school) {
            $studentQuery->orWhere(function ($query) use ($school) {
                $query->where('school_id', $school->id);
                if ($school->subscription_code) {
                    $query->orWhere('school_subscription_code', $school->subscription_code);
                }
            });
        }

        $internshipQuery = Internship::query()->where('status', '!=', 'closed');
        if ($organization->type === 'school' && $school) {
            $internshipQuery->where('school_id', $school->id);
        }
        if ($organization->type === 'company' && $company) {
            $internshipQuery->where('company_id', $company->id);
        }

        return [
            'school' => [
                'coordinators' => $organization->type === 'school' ? $activeMemberCount : 0,
                'students' => $organization->type === 'school' ? (clone $studentQuery)->count() : 0,
            ],
            'company' => [
                'accounts' => $organization->type === 'company' ? $activeMemberCount : 0,
                'internships' => $organization->type === 'company' ? $internshipQuery->count() : 0,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function evaluateOverages(Organization $organization): array
    {
        $plan = $this->getPlanForOrganization($organization);
        if (! $plan) {
            return ['requiresAction' => false, 'items' => []];
        }

        $usage = $this->usageSummary($organization);
        $items = [];

        if ($organization->type === 'school') {
            $items = array_values(array_filter([
                $this->makeOverageItem(
                    'school.coordinators',
                    'school',
                    'coordinators',
                    'School coordinator accounts',
                    $usage['school']['coordinators'],
                    (int) $plan->school_coordinators_limit
                ),
                $this->makeOverageItem(
                    'school.students',
                    'school',
                    'students',
                    'Student accounts',
                    $usage['school']['students'],
                    (int) $plan->school_students_limit
                ),
            ]));
        }

        if ($organization->type === 'company') {
            $items = array_values(array_filter([
                $this->makeOverageItem(
                    'company.accounts',
                    'company',
                    'accounts',
                    'Company accounts',
                    $usage['company']['accounts'],
                    (int) $plan->company_accounts_limit
                ),
                $this->makeOverageItem(
                    'company.internships',
                    'company',
                    'internships',
                    'Active internships',
                    $usage['company']['internships'],
                    (int) $plan->company_internships_limit
                ),
            ]));
        }

        return [
            'requiresAction' => $items !== [],
            'items' => $items,
        ];
    }

    public function syncOrganizationCompliance(Organization $organization): void
    {
        $organization->loadMissing('owner', 'subscription');

        $settings = $organization->settings ?? [];
        $overages = $this->evaluateOverages($organization);
        $settings['subscription_overages'] = $overages;
        $organization->settings = $settings;
        $organization->save();

        if ($overages['requiresAction']) {
            $this->notifyOrganizationOwner($organization, $overages);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getOverageForResource(Organization $organization, string $resourceKey): ?array
    {
        $overages = $this->evaluateOverages($organization);
        $items = is_array($overages['items'] ?? null) ? $overages['items'] : [];

        foreach ($items as $item) {
            if (($item['key'] ?? null) === $resourceKey) {
                return $item;
            }
        }

        return null;
    }

    public function wouldExceedAfterIncrement(Organization $organization, string $resourceKey, int $increment = 1): bool
    {
        $plan = $this->getPlanForOrganization($organization);
        if (! $plan) {
            return false;
        }

        $usage = $this->usageSummary($organization);

        return match ($resourceKey) {
            'school.coordinators' => $this->isLimited((int) $plan->school_coordinators_limit, (int) $usage['school']['coordinators'] + $increment),
            'school.students' => $this->isLimited((int) $plan->school_students_limit, (int) $usage['school']['students'] + $increment),
            'company.accounts' => $this->isLimited((int) $plan->company_accounts_limit, (int) $usage['company']['accounts'] + $increment),
            'company.internships' => $this->isLimited((int) $plan->company_internships_limit, (int) $usage['company']['internships'] + $increment),
            default => false,
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private function makeOverageItem(
        string $key,
        string $scope,
        string $resource,
        string $label,
        int $current,
        int $limit
    ): ?array {
        if ($limit >= 999 || $current <= $limit) {
            return null;
        }

        return [
            'key' => $key,
            'scope' => $scope,
            'resource' => $resource,
            'label' => $label,
            'limit' => $limit,
            'current' => $current,
            'excess' => $current - $limit,
            'message' => sprintf(
                '%s is over the current subscription limit. Remove %d item%s to stay within the plan.',
                $label,
                $current - $limit,
                $current - $limit === 1 ? '' : 's'
            ),
        ];
    }

    private function isLimited(int $limit, int $current): bool
    {
        return $limit < 999 && $current > $limit;
    }

    /**
     * @param  array<string, mixed>  $overages
     */
    private function notifyOrganizationOwner(Organization $organization, array $overages): void
    {
        if (! $organization->owner_user_id) {
            return;
        }

        $items = is_array($overages['items'] ?? null) ? $overages['items'] : [];
        $summary = collect($items)
            ->map(fn (array $item) => (string) ($item['label'] ?? 'Limit'))
            ->implode(', ');

        $metadata = [
            'type' => 'subscription_overage',
            'organizationId' => (string) $organization->id,
            'items' => $items,
        ];

        $exists = Notification::query()
            ->where('user_id', $organization->owner_user_id)
            ->where('channel', 'in_app')
            ->where('metadata->type', 'subscription_overage')
            ->where('metadata->organizationId', (string) $organization->id)
            ->whereNull('read_at')
            ->exists();

        if ($exists) {
            return;
        }

        Notification::query()->create([
            'user_id' => $organization->owner_user_id,
            'title' => 'Subscription limits need attention',
            'body' => $summary !== ''
                ? 'Your organization is over plan limits for '.$summary.'. Review your accounts and remove the excess items.'
                : 'Your organization is over the current subscription limits. Review your accounts and remove the excess items.',
            'channel' => 'in_app',
            'metadata' => $metadata,
        ]);
    }
}
