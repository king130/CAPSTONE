<?php

namespace App\Services\Contracts;

use App\Models\ContractType;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Collection;

class ContractTypeResolver
{
    /**
     * @return Collection<int, ContractType>
     */
    public function forUser(User $actor, ?Organization $requester, ?Organization $partner): Collection
    {
        $organizationIds = collect([$requester?->id, $partner?->id])->filter()->values();

        $types = ContractType::query()
            ->where('is_active', true)
            ->where(function ($query) use ($actor, $organizationIds) {
                $query->where('scope', 'global');
                if ($organizationIds->isNotEmpty()) {
                    $query->orWhere(function ($organizationQuery) use ($actor, $organizationIds) {
                        $organizationQuery
                            ->whereIn('organization_id', $organizationIds)
                            ->where('created_by_user_id', $actor->id);
                    });
                }
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $precedence = [
            $requester?->id => 3,
            $partner?->id => 2,
            null => 1,
        ];

        return $types
            ->groupBy('slug')
            ->map(function (Collection $group) use ($precedence) {
                return $group
                    ->sortByDesc(fn (ContractType $type) => $precedence[$type->organization_id] ?? 0)
                    ->first();
            })
            ->filter()
            ->values();
    }
}
