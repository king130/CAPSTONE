<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AdminController extends Controller
{
    public function users(Request $request)
    {
        if (! $this->canManageUsers($request->user())) {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $rows = User::query()
            ->with(['platformRole.permissions', 'organizationMemberships.role', 'organizationMemberships.organization'])
            ->orderByDesc('created_at')
            ->get()
            ->map(function (User $u) {
                $primaryMembership = $u->activeMemberships()->first();
            return [
                'uid' => (string) $u->id,
                'email' => $u->email,
                'displayName' => $u->name,
                'role' => $u->effectiveAppRole(),
                'legacyRole' => $u->role,
                'accessScope' => $u->isPlatformAdmin() ? 'platform' : ($primaryMembership ? 'organization' : 'personal'),
                'platformRole' => $u->platformRole ? [
                    'slug' => $u->platformRole->slug,
                    'name' => $u->platformRole->name,
                ] : null,
                'organization' => $primaryMembership ? [
                    'id' => (string) $primaryMembership->organization->id,
                    'name' => $primaryMembership->organization->name,
                    'type' => $primaryMembership->organization->type,
                    'memberRole' => $primaryMembership->role->slug,
                    'memberRoleName' => $primaryMembership->role->name,
                ] : null,
                'isTemporary' => $u->is_temporary,
                'isActive' => $u->is_active,
                'profile' => $u->profile ?? [],
                'createdAt' => $u->created_at?->toIso8601String(),
            ];
        });

        return response()->json(['data' => $rows]);
    }

    public function updateUser(Request $request, User $user)
    {
        if (! $this->canManageUsers($request->user())) {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $data = $request->validate([
            'role' => ['sometimes', 'string', 'max:32'],
            'isActive' => ['sometimes', 'boolean'],
            'profileSetupComplete' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('role', $data)) {
            $user->role = $data['role'];
        }
        if (array_key_exists('isActive', $data)) {
            $user->is_active = $data['isActive'];
        }
        if (array_key_exists('profileSetupComplete', $data)) {
            $user->profile_setup_complete = $data['profileSetupComplete'];
        }

        $user->save();

        $auth = new AuthController;

        $fresh = $user->fresh([
            'student',
            'company',
            'school',
            'platformRole.permissions',
            'organizationMemberships.role.permissions',
            'organizationMemberships.organization.subscription',
        ]);

        return response()->json($auth->formatUserProfile($fresh));
    }

    private function canManageUsers(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->role === 'admin') {
            return true;
        }

        return in_array('platform.manage_users', $user->platformPermissionKeys(), true);
    }
}
