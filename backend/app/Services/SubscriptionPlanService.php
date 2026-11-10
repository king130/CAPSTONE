<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Internship;
use App\Models\Notification;
use App\Models\Organization;
use App\Models\OrganizationMedia;
use App\Models\OrganizationMembership;
use App\Models\School;
use App\Models\Student;
use App\Models\SubscriptionPlanDefinition;

class SubscriptionPlanService
{
    public function getAllPlans(bool $selectableOnly = true)
    {
        $query = SubscriptionPlanDefinition::query()
            ->where('is_active', true)
            ->orderByRaw("case slug when 'free' then 1 when 'standard' then 2 when 'premium' then 3 else 99 end");

        if ($selectableOnly) {
            $query->where(function ($q) {
                $q->where('is_selectable', true)->orWhereNull('is_selectable');
            })->where(function ($q) {
                $q->where('is_legacy', false)->orWhereNull('is_legacy');
            })->where('slug', '!=', 'free');
        }

        return $query->get();
    }

    public function getPlanForOrganization(Organization $organization): ?SubscriptionPlanDefinition
    {
        $organization->loadMissing('subscription');

        $access = app(SubscriptionAccessService::class)->resolve($organization);
        $rawPlan = strtolower(trim((string) ($organization->subscription?->plan ?: ''))) ?: '';
        $accessStatus = strtolower(trim((string) ($access['status'] ?? '')));
        $planSlug = strtolower(trim((string) ($access['plan'] ?? $rawPlan))) ?: '';
        $settings = is_array($organization->settings) ? $organization->settings : [];
        $pending = is_array($settings['pending_plan_change'] ?? null) ? $settings['pending_plan_change'] : null;
        $previousPlan = $pending
            ? strtolower(trim((string) ($pending['previousPlan'] ?? '')))
            : '';

        // Unverified + free always use free-plan seat limits (never read-only lock).
        if (in_array($accessStatus, [
            SubscriptionAccessService::STATUS_UNVERIFIED,
            SubscriptionAccessService::STATUS_FREE,
        ], true)) {
            $planSlug = 'free';
        } elseif ($accessStatus === SubscriptionAccessService::STATUS_TRIAL) {
            $planSlug = (string) config('subscription.trial_plan', 'standard') ?: 'standard';
        } elseif ($accessStatus === SubscriptionAccessService::STATUS_PENDING_PAYMENT) {
            // Checkout in flight: keep CURRENT entitlements; never grant target before verify.
            if ($previousPlan !== '') {
                $planSlug = $previousPlan === 'free' ? 'free' : $previousPlan;
            } elseif ($rawPlan !== '' && $rawPlan !== 'free') {
                $planSlug = $rawPlan;
            } else {
                $planSlug = 'free';
            }
        } elseif (in_array($accessStatus, [
            SubscriptionAccessService::STATUS_ACTIVE,
            SubscriptionAccessService::STATUS_PAST_DUE,
            SubscriptionAccessService::STATUS_EXPIRED,
        ], true)) {
            // Keep the paid (or last paid) plan definition for limit display / enforcement.
            $planSlug = $rawPlan !== '' && $rawPlan !== 'free'
                ? $rawPlan
                : (string) ($access['plan'] ?? 'standard');
        }

        if ($planSlug === '') {
            $planSlug = 'free';
        }

        return SubscriptionPlanDefinition::query()
            ->where('is_active', true)
            ->where('slug', $planSlug)
            ->first()
            ?? SubscriptionPlanDefinition::query()->where('slug', 'standard')->first()
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
            'isSelectable' => (bool) ($plan->is_selectable ?? ($plan->slug !== 'free')),
            'isLegacy' => (bool) ($plan->is_legacy ?? ($plan->slug === 'free')),
            'features' => [
                'school' => array_values($plan->school_features ?? []),
                'company' => array_values($plan->company_features ?? []),
            ],
            'limits' => [
                'school' => [
                    'coordinators' => (int) $plan->school_coordinators_limit,
                    'students' => (int) $plan->school_students_limit,
                    'photos' => (int) ($plan->organization_photos_limit ?? 3),
                ],
                'company' => [
                    'accounts' => (int) $plan->company_accounts_limit,
                    'internships' => (int) $plan->company_internships_limit,
                    'photos' => (int) ($plan->organization_photos_limit ?? 3),
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

        $seatRoles = \App\Support\OrganizationTypes::accountSeatRolesForOrganizationType($organization->type);
        $activeMemberCount = OrganizationMembership::query()
            ->where('organization_id', $organization->id)
            ->where('status', 'active')
            ->whereHas('user', function ($query) use ($seatRoles) {
                $query->where('is_active', true)->whereIn('role', $seatRoles);
            })
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
        $isHost = \App\Support\OrganizationTypes::isHostType($organization->type);
        if ($isHost && $company) {
            $internshipQuery->where('company_id', $company->id);
        }

        $photoCount = OrganizationMedia::query()->where('organization_id', $organization->id)->count();

        return [
            'school' => [
                'coordinators' => $organization->type === 'school' ? $activeMemberCount : 0,
                'students' => $organization->type === 'school' ? (clone $studentQuery)->count() : 0,
                'photos' => $organization->type === 'school' ? $photoCount : 0,
            ],
            'company' => [
                'accounts' => $isHost ? $activeMemberCount : 0,
                'internships' => $isHost ? $internshipQuery->count() : 0,
                'photos' => $isHost ? $photoCount : 0,
            ],
            'organization' => [
                'photos' => $photoCount,
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

        $photoLimit = (int) ($plan->organization_photos_limit ?? 3);

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
                $this->makeOverageItem(
                    'organization.photos',
                    'organization',
                    'photos',
                    'Organization photos',
                    (int) ($usage['organization']['photos'] ?? 0),
                    $photoLimit
                ),
            ]));
        }

        if (\App\Support\OrganizationTypes::isHostType($organization->type)) {
            $accountLabel = $organization->type === 'municipal_hall'
                ? 'Municipal Hall accounts'
                : 'Company accounts';
            $items = array_values(array_filter([
                $this->makeOverageItem(
                    'company.accounts',
                    'company',
                    'accounts',
                    $accountLabel,
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
                $this->makeOverageItem(
                    'organization.photos',
                    'organization',
                    'photos',
                    'Organization photos',
                    (int) ($usage['organization']['photos'] ?? 0),
                    $photoLimit
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

        $this->reconcileAccountSeats($organization);

        $settings = $organization->fresh()->settings ?? [];
        $overages = $this->evaluateOverages($organization->fresh());
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
            'organization.photos' => $this->isLimited(
                (int) ($plan->organization_photos_limit ?? 3),
                (int) ($usage['organization']['photos'] ?? 0) + $increment
            ),
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
     * Keep the owner plus the oldest seats that fit the plan.
     * Extra coordinator or company accounts are paused, and restored when the plan covers them.
     */
    public function reconcileAccountSeats(Organization $organization): void
    {
        if (! in_array($organization->type, ['school', 'company', 'municipal_hall'], true)) {
            return;
        }

        $plan = $this->getPlanForOrganization($organization);
        if (! $plan) {
            return;
        }

        $limit = $organization->type === 'school'
            ? (int) $plan->school_coordinators_limit
            : (int) $plan->company_accounts_limit;
        $label = $organization->type === 'school'
            ? 'School coordinator accounts'
            : ($organization->type === 'municipal_hall' ? 'Municipal Hall accounts' : 'Company accounts');
        $key = $organization->type === 'school' ? 'school.coordinators' : 'company.accounts';
        $unlimited = $limit >= 999;

        $seatRoles = \App\Support\OrganizationTypes::accountSeatRolesForOrganizationType($organization->type);
        $memberships = OrganizationMembership::query()
            ->with('user')
            ->where('organization_id', $organization->id)
            ->whereHas('user', fn ($query) => $query->whereIn('role', $seatRoles))
            ->orderBy('id')
            ->get();

        $ownerId = $organization->owner_user_id ? (int) $organization->owner_user_id : null;

        $candidates = $memberships->filter(function (OrganizationMembership $membership) {
            $override = is_array($membership->permissions_override) ? $membership->permissions_override : [];
            if (! empty($override['planLimitHold'])) {
                return true;
            }

            return $membership->status === 'active' && $membership->user?->is_active;
        })->sortBy(function (OrganizationMembership $membership) use ($ownerId) {
            $ownerRank = $ownerId !== null && (int) $membership->user_id === $ownerId ? 0 : 1;

            return sprintf('%d-%010d', $ownerRank, $membership->id);
        })->values();

        $keepCount = $unlimited ? $candidates->count() : max($limit, $ownerId ? 1 : 0);
        $keepIds = $candidates->take($keepCount)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $paused = 0;

        foreach ($candidates as $membership) {
            $user = $membership->user;
            if (! $user) {
                continue;
            }

            $override = is_array($membership->permissions_override) ? $membership->permissions_override : [];
            $keep = in_array((int) $membership->id, $keepIds, true);

            if ($keep) {
                $changed = ! empty($override['planLimitHold']) || $membership->status !== 'active' || ! $user->is_active;
                unset($override['planLimitHold']);
                if ($changed) {
                    $membership->permissions_override = $override;
                    $membership->status = 'active';
                    $membership->save();
                    $user->forceFill(['is_active' => true])->save();
                }

                continue;
            }

            if ($ownerId !== null && (int) $user->id === $ownerId) {
                continue;
            }

            $override['planLimitHold'] = true;
            $membership->permissions_override = $override;
            $membership->status = 'inactive';
            $membership->save();
            $user->forceFill(['is_active' => false])->save();
            $user->tokens()->delete();
            $paused++;
        }

        $settings = is_array($organization->settings) ? $organization->settings : [];
        if ($paused > 0) {
            $settings['plan_seat_holds'] = [[
                'key' => $key,
                'label' => $label,
                'paused' => $paused,
                'limit' => $limit,
            ]];
        } else {
            unset($settings['plan_seat_holds']);
        }
        $organization->settings = $settings;
        $organization->save();
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
