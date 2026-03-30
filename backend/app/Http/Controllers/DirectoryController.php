<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use Illuminate\Http\Request;

class DirectoryController extends Controller
{
    public function index(Request $request, string $role)
    {
        if (! in_array($role, ['school', 'company'], true)) {
            return response()->json(['data' => []]);
        }

        $rows = Organization::query()
            ->where('type', $role)
            ->where('is_active', true)
            ->with(['owner', 'memberships.user', 'subscription'])
            ->orderBy('name')
            ->get()
            ->map(function (Organization $organization) use ($role) {
                $owner = $organization->owner;
                $profile = is_array($owner?->profile) ? $owner->profile : [];

                return [
                    'uid' => $owner ? (string) $owner->id : '',
                    'organizationId' => (string) $organization->id,
                    'displayName' => $owner?->name ?? $organization->name,
                    'role' => $role,
                    'orgName' => $organization->name,
                    'email' => $owner?->email,
                    'courses' => $profile['courses'] ?? [],
                    'memberCount' => $organization->memberships->count(),
                    'subscriptionStatus' => $organization->subscription?->status,
                ];
            });

        return response()->json(['data' => $rows]);
    }
}
