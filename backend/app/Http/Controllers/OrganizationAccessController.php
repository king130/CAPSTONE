<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class OrganizationAccessController extends Controller
{
    public function index(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        $membership = $user->primaryOrganizationMembership();

        if (! $membership || ! $membership->organization) {
            return response()->json(['message' => 'No active organization context found.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (! $this->canManageOrganizationAccess($membership)) {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $organization = $membership->organization->load([
            'memberships.user',
            'memberships.role.permissions',
        ]);

        $roles = Role::query()
            ->with('permissions')
            ->where('scope', 'organization')
            ->where('organization_type', $organization->type)
            ->orderBy('name')
            ->get();

        return response()->json([
            'organization' => [
                'id' => (string) $organization->id,
                'name' => $organization->name,
                'type' => $organization->type,
            ],
            'roles' => $roles->map(fn (Role $role) => $this->serializeRole($role))->values(),
            'members' => $organization->memberships
                ->sortBy(fn (OrganizationMembership $item) => strtolower($item->user?->name ?? ''))
                ->values()
                ->map(fn (OrganizationMembership $item) => $this->serializeMembership($item))
                ->values(),
        ]);
    }

    public function updateMember(Request $request, OrganizationMembership $membership)
    {
        /** @var User $user */
        $user = $request->user();
        $actorMembership = $user->primaryOrganizationMembership();

        if (! $actorMembership || ! $actorMembership->organization) {
            return response()->json(['message' => 'No active organization context found.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ((int) $membership->organization_id !== (int) $actorMembership->organization_id) {
            return response()->json(['message' => 'You can only manage members in your organization.'], Response::HTTP_FORBIDDEN);
        }

        if (! $this->canManageOrganizationAccess($actorMembership)) {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $data = $request->validate([
            'roleId' => ['sometimes', 'integer', 'exists:roles,id'],
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'grantPermissions' => ['sometimes', 'array'],
            'grantPermissions.*' => ['string', 'max:255'],
            'denyPermissions' => ['sometimes', 'array'],
            'denyPermissions.*' => ['string', 'max:255'],
            'status' => ['sometimes', 'in:active,inactive,pending'],
        ]);

        if (array_key_exists('roleId', $data)) {
            $role = Role::query()
                ->where('id', $data['roleId'])
                ->where('scope', 'organization')
                ->where('organization_type', $actorMembership->organization->type)
                ->first();

            if (! $role) {
                return response()->json(['message' => 'The selected role is not valid for this organization.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $membership->role_id = $role->id;
        }

        if (array_key_exists('title', $data)) {
            $membership->title = $data['title'];
        }

        if (array_key_exists('status', $data)) {
            $membership->status = $data['status'];
        }

        if (array_key_exists('grantPermissions', $data) || array_key_exists('denyPermissions', $data)) {
            $membership->permissions_override = [
                'grant' => array_values(array_unique($data['grantPermissions'] ?? ($membership->permissions_override['grant'] ?? []))),
                'deny' => array_values(array_unique($data['denyPermissions'] ?? ($membership->permissions_override['deny'] ?? []))),
            ];
        }

        $membership->save();
        $membership->load(['user', 'role.permissions', 'organization']);

        return response()->json([
            'member' => $this->serializeMembership($membership),
        ]);
    }

    public function storeMember(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        $actorMembership = $user->primaryOrganizationMembership();

        if (! $actorMembership || ! $actorMembership->organization) {
            return response()->json(['message' => 'No active organization context found.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (! $this->canManageOrganizationAccess($actorMembership)) {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'roleId' => ['required', 'integer', 'exists:roles,id'],
            'title' => ['nullable', 'string', 'max:255'],
        ]);

        $organization = $actorMembership->organization;
        $role = Role::query()
            ->where('id', $data['roleId'])
            ->where('scope', 'organization')
            ->where('organization_type', $organization->type)
            ->first();

        if (! $role) {
            return response()->json(['message' => 'The selected role is not valid for this organization.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $tempPassword = $this->generateTemporaryPassword();
        $legacyRole = $organization->type;

        $newUser = User::query()->create([
            'name' => trim($data['name']),
            'email' => strtolower(trim($data['email'])),
            'password' => $tempPassword,
            'role' => $legacyRole,
            'profile' => $this->buildProfileForOrganizationUser($organization, $data['title'] ?? null),
            'is_active' => true,
            'is_temporary' => true,
            'must_change_password' => true,
            'profile_setup_complete' => true,
        ]);

        $membership = OrganizationMembership::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $newUser->id,
            'role_id' => $role->id,
            'status' => 'active',
            'title' => $data['title'] ?? $role->name,
            'invited_by_user_id' => $user->id,
        ]);

        $membership->load(['user', 'role.permissions', 'organization']);

        return response()->json([
            'member' => $this->serializeMembership($membership),
            'temporaryPassword' => $tempPassword,
        ], Response::HTTP_CREATED);
    }

    private function canManageOrganizationAccess(OrganizationMembership $membership): bool
    {
        return in_array('org.manage_roles', $membership->effectivePermissions(), true)
            || in_array('org.manage_members', $membership->effectivePermissions(), true);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildProfileForOrganizationUser(Organization $organization, ?string $title): array
    {
        if ($organization->type === 'school') {
            return [
                'institutionName' => $organization->name,
                'position' => $title,
            ];
        }

        return [
            'companyName' => $organization->name,
            'contactPersonTitle' => $title,
        ];
    }

    private function generateTemporaryPassword(): string
    {
        return 'Temp@'.strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeRole(Role $role): array
    {
        return [
            'id' => (string) $role->id,
            'name' => $role->name,
            'slug' => $role->slug,
            'scope' => $role->scope,
            'organizationType' => $role->organization_type,
            'permissions' => $role->permissions->pluck('key')->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeMembership(OrganizationMembership $membership): array
    {
        $membership->loadMissing(['user', 'role.permissions', 'organization']);

        return [
            'id' => (string) $membership->id,
            'status' => $membership->status,
            'title' => $membership->title,
            'user' => [
                'id' => (string) $membership->user->id,
                'name' => $membership->user->name,
                'email' => $membership->user->email,
                'legacyRole' => $membership->user->role,
                'isActive' => $membership->user->is_active,
            ],
            'role' => $this->serializeRole($membership->role),
            'permissionsOverride' => [
                'grant' => array_values($membership->permissions_override['grant'] ?? []),
                'deny' => array_values($membership->permissions_override['deny'] ?? []),
            ],
            'effectivePermissions' => $membership->effectivePermissions(),
        ];
    }
}
