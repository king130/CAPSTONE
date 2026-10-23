<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Contract;
use App\Models\Organization;
use App\Models\School;
use App\Models\User;
use App\Support\OrganizationTypes;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class DirectoryController extends Controller
{
    public function index(Request $request, string $role)
    {
        if (! in_array($role, ['school', 'company'], true)) {
            return response()->json(['data' => []]);
        }

        /** @var User|null $viewer */
        $viewer = $request->user();
        $relationshipByPartnerUserId = $this->agreementRelationshipsForViewer($viewer, $role);

        $orgTypes = $role === 'company' ? OrganizationTypes::hostTypes() : [$role];

        $organizations = Organization::query()
            ->whereIn('type', $orgTypes)
            ->where('is_active', true)
            ->with(['owner', 'memberships.user', 'subscription'])
            ->orderBy('name')
            ->get();

        $verificationByOrgId = $this->verificationStatusesForOrganizations($organizations, $role);

        $rows = $organizations->map(function (Organization $organization) use ($role, $relationshipByPartnerUserId, $verificationByOrgId) {
            $owner = $organization->owner;
            $profile = is_array($owner?->profile) ? $owner->profile : [];
            $ownerId = $owner ? (string) $owner->id : '';
            $relationship = $ownerId !== ''
                ? ($relationshipByPartnerUserId[$ownerId] ?? null)
                : null;

            $status = is_array($relationship) ? ($relationship['status'] ?? null) : null;
            $verificationStatus = strtolower(trim((string) ($verificationByOrgId[(string) $organization->id] ?? 'pending')));
            $verified = $verificationStatus === 'approved';
            $selectable = $verified && ! in_array($status, ['active', 'pending'], true);

            return [
                'uid' => $ownerId,
                'organizationId' => (string) $organization->id,
                'displayName' => $owner?->name ?? $organization->name,
                'role' => $role,
                'orgName' => $organization->name,
                'email' => $owner?->email,
                'courses' => $profile['courses'] ?? [],
                'memberCount' => $organization->memberships->count(),
                'subscriptionStatus' => $organization->subscription?->status,
                'verificationStatus' => $verificationStatus,
                // Agreement relationship relative to the authenticated viewer (school/company).
                'agreementStatus' => $status,
                'agreementSelectable' => $selectable,
                'agreementStatusLabel' => match ($status) {
                    'active' => 'Agreement already active',
                    'pending' => 'Agreement request pending',
                    default => null,
                },
            ];
        });

        return response()->json(['data' => $rows]);
    }

    /**
     * @param  Collection<int, Organization>  $organizations
     * @return array<string, string>
     */
    private function verificationStatusesForOrganizations(Collection $organizations, string $role): array
    {
        $orgIds = $organizations->pluck('id')->filter()->values();
        if ($orgIds->isEmpty()) {
            return [];
        }

        if ($role === 'school') {
            return School::query()
                ->whereIn('organization_id', $orgIds)
                ->pluck('verification_status', 'organization_id')
                ->mapWithKeys(fn ($status, $orgId) => [(string) $orgId => (string) ($status ?: 'pending')])
                ->all();
        }

        return Company::query()
            ->whereIn('organization_id', $orgIds)
            ->pluck('verification_status', 'organization_id')
            ->mapWithKeys(fn ($status, $orgId) => [(string) $orgId => (string) ($status ?: 'pending')])
            ->all();
    }

    /**
     * Map counterparty owner user id → relationship status for the viewer.
     * Prefer "active" over "pending" when both somehow exist.
     *
     * @return array<string, array{status: string}>
     */
    private function agreementRelationshipsForViewer(?User $viewer, string $listedRole): array
    {
        if (! $viewer) {
            return [];
        }

        $viewerRole = $viewer->effectiveAppRole();
        if (! in_array($viewerRole, ['school', 'company'], true)) {
            return [];
        }

        // Directory lists the opposite role; only enrich when the viewer is requesting that side.
        if ($viewerRole === 'school' && $listedRole !== 'company') {
            return [];
        }
        if ($viewerRole === 'company' && $listedRole !== 'school') {
            return [];
        }

        $viewerOrg = $viewer->activeOrganization();
        $viewerOwnerId = (int) ($viewerOrg?->owner_user_id ?? $viewer->id);
        if ($viewerOwnerId <= 0) {
            return [];
        }

        $query = Contract::query()->whereIn('status', ['pending', 'active']);

        if ($viewerRole === 'company') {
            $query->where('company_user_id', $viewerOwnerId);
            $partnerKey = 'school_user_id';
        } else {
            $query->where('school_user_id', $viewerOwnerId);
            $partnerKey = 'company_user_id';
        }

        $map = [];
        foreach ($query->get(['status', 'school_user_id', 'company_user_id']) as $agreement) {
            $partnerUserId = (string) ($agreement->{$partnerKey} ?? '');
            if ($partnerUserId === '') {
                continue;
            }

            $status = strtolower(trim((string) $agreement->status));
            if (! in_array($status, ['pending', 'active'], true)) {
                continue;
            }

            // Prefer active when both statuses exist for the same counterparty.
            if (($map[$partnerUserId]['status'] ?? null) === 'active') {
                continue;
            }

            $map[$partnerUserId] = ['status' => $status];
        }

        return $map;
    }
}
