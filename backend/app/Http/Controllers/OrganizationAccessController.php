<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\SubscriptionPlanService;
use App\Services\TenantRoleService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class OrganizationAccessController extends Controller
{
    public function __construct(
        private readonly SubscriptionPlanService $subscriptionPlans,
        private readonly TenantRoleService $tenantRoleService,
    )
    {
    }

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

        $this->tenantRoleService->ensureDefaultRoles($organization);

        $roles = Role::query()
            ->with('permissions')
            ->where(function ($query) use ($organization) {
                $query
                    ->where('tenant_id', $organization->id)
                    ->orWhere(function ($fallback) use ($organization) {
                        $fallback
                            ->whereNull('tenant_id')
                            ->where('scope', 'organization')
                            ->where('organization_type', $organization->type);
                    });
            })
            ->orderBy('name')
            ->get();

        $permissionKeys = Permission::catalogOrganizationPermissionKeysForUi();

        return response()->json([
            'organization' => [
                'id' => (string) $organization->id,
                'name' => $organization->name,
                'type' => $organization->type,
            ],
            'permissionKeys' => $permissionKeys,
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

        $actorMembership->loadMissing('role.permissions');

        $assignableKeys = Permission::assignableOrganizationPermissionKeys();

        $data = $request->validate([
            'roleId' => ['sometimes', 'integer', 'exists:roles,id'],
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'grantPermissions' => ['sometimes', 'array'],
            'grantPermissions.*' => ['string', 'max:255', Rule::in($assignableKeys)],
            'denyPermissions' => ['sometimes', 'array'],
            'denyPermissions.*' => ['string', 'max:255', Rule::in($assignableKeys)],
            'status' => ['sometimes', 'in:active,inactive,pending'],
        ]);

        if (array_key_exists('grantPermissions', $data)) {
            $grantKeys = array_values(array_unique($data['grantPermissions'] ?? []));
            if (! $actorMembership->mayGrantOrganizationPermissionKeys($grantKeys)) {
                return response()->json([
                    'message' => 'You cannot grant one or more of the selected permissions.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        if (array_key_exists('roleId', $data)) {
            $role = Role::query()
                ->where('id', $data['roleId'])
                ->where(function ($query) use ($actorMembership) {
                    $query
                        ->where('tenant_id', $actorMembership->organization->id)
                        ->orWhere(function ($fallback) use ($actorMembership) {
                            $fallback
                                ->whereNull('tenant_id')
                                ->where('scope', 'organization')
                                ->where('organization_type', $actorMembership->organization->type);
                        });
                })
                ->first();

            if (! $role) {
                return response()->json(['message' => 'The selected role is not valid for this organization.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $role->loadMissing('permissions');

            if (! $actorMembership->mayAssignTenantRole($role)) {
                return response()->json([
                    'message' => 'You cannot assign this role with your current permissions.',
                ], Response::HTTP_FORBIDDEN);
            }

            $membership->role_id = $role->id;
        }

        if (array_key_exists('title', $data)) {
            $membership->title = $data['title'];
        }

        if (array_key_exists('status', $data)) {
            if (
                $data['status'] === 'active'
                && $membership->status !== 'active'
                && $this->wouldExceedAccountLimit($actorMembership->organization)
            ) {
                $overage = $this->accountOverageForOrganization($actorMembership->organization);

                return response()->json([
                    'message' => $overage['message'] ?? 'Your organization is already at the account limit for the current plan.',
                    'overage' => $overage,
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $membership->status = $data['status'];
        }

        if (array_key_exists('grantPermissions', $data) || array_key_exists('denyPermissions', $data)) {
            $membership->permissions_override = [
                'grant' => Permission::normalizeOrganizationPermissionKeys(
                    array_values(array_unique($data['grantPermissions'] ?? ($membership->permissions_override['grant'] ?? [])))
                ),
                'deny' => Permission::normalizeOrganizationPermissionKeys(
                    array_values(array_unique($data['denyPermissions'] ?? ($membership->permissions_override['deny'] ?? [])))
                ),
            ];
        }

        $membership->save();
        $membership->load(['user', 'role.permissions', 'organization']);
        $this->subscriptionPlans->syncOrganizationCompliance($actorMembership->organization);

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

        $actorMembership->loadMissing('role.permissions');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'roleId' => ['required', 'integer', 'exists:roles,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        $organization = $actorMembership->organization;
        if ($this->wouldExceedAccountLimit($organization)) {
            $overage = $this->accountOverageForOrganization($organization);

            return response()->json([
                'message' => $overage['message'] ?? 'Your organization has reached the account limit for the current plan.',
                'overage' => $overage,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $role = Role::query()
            ->where('id', $data['roleId'])
            ->where(function ($query) use ($organization) {
                $query
                    ->where('tenant_id', $organization->id)
                    ->orWhere(function ($fallback) use ($organization) {
                        $fallback
                            ->whereNull('tenant_id')
                            ->where('scope', 'organization')
                            ->where('organization_type', $organization->type);
                    });
            })
            ->first();

        if (! $role) {
            return response()->json(['message' => 'The selected role is not valid for this organization.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($organization->type === 'school' && $role->slug !== 'intern') {
            return response()->json([
                'message' => 'School accounts can only create intern accounts from organization access.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $role->loadMissing('permissions');

        if (! $actorMembership->mayAssignTenantRole($role)) {
            return response()->json([
                'message' => 'You cannot assign this role with your current permissions.',
            ], Response::HTTP_FORBIDDEN);
        }

        $useGeneratedPassword = ! filled($data['password'] ?? null);
        $plainPassword = $useGeneratedPassword
            ? $this->generateTemporaryPassword()
            : (string) $data['password'];
        $legacyRole = $organization->type === 'school' ? 'student' : $organization->type;

        $newUser = User::query()->create([
            'name' => trim($data['name']),
            'email' => strtolower(trim($data['email'])),
            'password' => $plainPassword,
            'role' => $legacyRole,
            'profile' => $this->buildProfileForOrganizationUser($organization, $data['title'] ?? null),
            'is_active' => true,
            'is_temporary' => $useGeneratedPassword,
            'must_change_password' => $useGeneratedPassword,
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

        $newUser->forceFill([
            'tenant_id' => $organization->id,
            'role_id' => $role->id,
        ])->save();

        $membership->load(['user', 'role.permissions', 'organization']);
        $this->subscriptionPlans->syncOrganizationCompliance($organization);

        return response()->json([
            'member' => $this->serializeMembership($membership),
            'temporaryPassword' => $useGeneratedPassword ? $plainPassword : null,
        ], Response::HTTP_CREATED);
    }

    private function canManageOrganizationAccess(OrganizationMembership $membership): bool
    {
        $membership->loadMissing('organization');
        $organization = $membership->organization;
        if ($organization && (int) ($organization->owner_user_id ?? 0) === (int) $membership->user_id) {
            return true;
        }

        $permissions = $membership->effectivePermissions();

        return in_array('org.manage_roles', $permissions, true)
            || in_array('org.manage_members', $permissions, true)
            || in_array('manage_roles', $permissions, true)
            || in_array('manage_permissions', $permissions, true);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildProfileForOrganizationUser(Organization $organization, ?string $title): array
    {
        if ($organization->type === 'school') {
            return [
                'institutionName' => $organization->name,
                'schoolName' => $organization->name,
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

    private function wouldExceedAccountLimit(Organization $organization): bool
    {
        $key = $organization->type === 'school' ? 'school.coordinators' : 'company.accounts';

        return $this->subscriptionPlans->wouldExceedAfterIncrement($organization, $key);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function accountOverageForOrganization(Organization $organization): ?array
    {
        $key = $organization->type === 'school' ? 'school.coordinators' : 'company.accounts';

        return $this->subscriptionPlans->getOverageForResource($organization, $key) ?? [
            'key' => $key,
            'message' => 'Your organization has reached the account limit for the current subscription plan.',
        ];
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
            'permissions' => Permission::normalizeOrganizationPermissionKeys($role->permissions->pluck('key')->all()),
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
                'grant' => Permission::normalizeOrganizationPermissionKeys(
                    array_values($membership->permissions_override['grant'] ?? [])
                ),
                'deny' => Permission::normalizeOrganizationPermissionKeys(
                    array_values($membership->permissions_override['deny'] ?? [])
                ),
            ],
            'effectivePermissions' => Permission::normalizeOrganizationPermissionKeys($membership->effectivePermissions()),
        ];
    }
}
