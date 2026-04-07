<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\OrganizationAccountProvisioner;
use App\Services\TenantRoleService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TenantRbacController extends Controller
{
    public function __construct(
        private readonly TenantRoleService $tenantRoleService,
        private readonly OrganizationAccountProvisioner $organizationAccountProvisioner,
    )
    {
    }

    public function tenants(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        if ($this->isSystemAdmin($user)) {
            $tenants = Organization::query()
                ->select(['id', 'name', 'type'])
                ->withCount(['memberships', 'roles'])
                ->orderBy('type')
                ->orderBy('name')
                ->get();

            return response()->json([
                'tenants' => $tenants->map(fn (Organization $tenant) => $this->serializeTenant($tenant))->values(),
            ]);
        }

        $membership = $user->primaryOrganizationMembership();
        if (! $membership?->organization || ! $this->canManageTenantRoles($user, $membership->organization)) {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $tenant = $membership->organization->loadCount(['memberships', 'roles']);
        $this->tenantRoleService->ensureDefaultRoles($tenant);
        $tenant->refresh()->loadCount(['memberships', 'roles']);

        return response()->json([
            'tenants' => [$this->serializeTenant($tenant)],
        ]);
    }

    public function index(Request $request, Organization $tenant)
    {
        /** @var User $user */
        $user = $request->user();

        if (! $this->canManageTenantRoles($user, $tenant)) {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $this->tenantRoleService->ensureDefaultRoles($tenant);

        $roles = Role::query()
            ->select(['id', 'tenant_id', 'name', 'slug', 'description'])
            ->where('tenant_id', $tenant->id)
            ->with(['permissions:id,key'])
            ->withCount('memberships')
            ->orderBy('name')
            ->get();

        $permissions = Permission::query()
            ->select(['id', 'key', 'description'])
            ->orderBy('key')
            ->get();

        $members = $tenant->memberships()
            ->select(['id', 'organization_id', 'user_id', 'role_id', 'status', 'title'])
            ->with([
                'user:id,name,email,role,is_active',
                'role:id,name,slug',
                'role.permissions:id,key',
            ])
            ->orderBy('title')
            ->get();

        return response()->json([
            'tenant' => $this->serializeTenant($tenant->loadCount(['memberships', 'roles'])),
            'roles' => $roles->map(fn (Role $role) => $this->serializeRole($role))->values(),
            'members' => $members->map(fn (OrganizationMembership $membership) => $this->serializeMembership($membership))->values(),
            'permissions' => $permissions->map(fn (Permission $permission) => [
                'id' => (string) $permission->id,
                'key' => $permission->key,
                'description' => $permission->description,
            ])->values(),
            'canManageAllTenants' => $this->isSystemAdmin($user),
        ]);
    }

    public function storeTenant(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        if (! $this->isSystemAdmin($user)) {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $data = $request->validate([
            'type' => ['required', 'in:company,school'],
            'name' => ['required', 'string', 'max:255'],
            'adminName' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'plan' => ['nullable', 'string', 'max:255'],
            'billingCycle' => ['nullable', 'string', 'max:255'],
        ]);

        $temporaryPassword = $this->organizationAccountProvisioner->generateTemporaryPassword();
        $profile = $data['type'] === 'school'
            ? [
                'institutionName' => $data['name'],
                'contactPersonName' => $data['adminName'],
                'position' => 'Primary Admin',
                'officialSchoolEmail' => $data['email'],
            ]
            : [
                'companyName' => $data['name'],
                'contactPersonName' => $data['adminName'],
                'contactPersonTitle' => 'Primary Admin',
                'companyEmail' => $data['email'],
            ];

        $created = $this->organizationAccountProvisioner->create([
            'fullName' => $data['adminName'],
            'email' => $data['email'],
            'temporaryPassword' => $temporaryPassword,
            'role' => $data['type'],
            'profile' => $profile,
            'subscriptionPlan' => $data['plan'] ?? 'free',
            'billingCycle' => $data['billingCycle'] ?? 'monthly',
        ]);

        /** @var Organization $organization */
        $organization = $created['organization'];

        return response()->json([
            'account' => $this->serializeTenant($organization->fresh()->loadCount(['memberships', 'roles'])),
            'temporaryPassword' => $created['temporaryPassword'],
        ], Response::HTTP_CREATED);
    }

    public function updateMember(Request $request, Organization $tenant, OrganizationMembership $membership)
    {
        /** @var User $user */
        $user = $request->user();

        if (! $this->canManageTenantRoles($user, $tenant)) {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        if ((int) $membership->organization_id !== (int) $tenant->id) {
            return response()->json(['message' => 'Member does not belong to the selected account.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $data = $request->validate([
            'roleId' => ['required', 'integer', 'exists:roles,id'],
        ]);

        $role = Role::query()
            ->where('id', $data['roleId'])
            ->where('tenant_id', $tenant->id)
            ->first();

        if (! $role) {
            return response()->json(['message' => 'The selected role is not valid for this account.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $membership->role_id = $role->id;
        $membership->title = $membership->title ?: $role->name;
        $membership->save();

        $membership->user?->forceFill([
            'tenant_id' => $tenant->id,
            'role_id' => $role->id,
        ])->save();

        return response()->json([
            'member' => $this->serializeMembership($membership->fresh(['user', 'role.permissions', 'organization'])),
        ]);
    }

    public function storeMember(Request $request, Organization $tenant)
    {
        /** @var User $user */
        $user = $request->user();

        if (! $this->canManageTenantMembers($user, $tenant)) {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'roleId' => ['required', 'integer', 'exists:roles,id'],
            'title' => ['nullable', 'string', 'max:255'],
        ]);

        $role = Role::query()
            ->where('id', $data['roleId'])
            ->where('tenant_id', $tenant->id)
            ->first();

        if (! $role) {
            return response()->json(['message' => 'The selected role is not valid for this account.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $temporaryPassword = 'Temp@'.strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        $legacyRole = $tenant->type === 'school' ? 'student' : $tenant->type;

        $profile = $tenant->type === 'school'
            ? [
                'institutionName' => $tenant->name,
                'schoolName' => $tenant->name,
                'position' => $data['title'] ?? $role->name,
            ]
            : [
                'companyName' => $tenant->name,
                'contactPersonTitle' => $data['title'] ?? $role->name,
            ];

        $newUser = User::query()->create([
            'name' => trim($data['name']),
            'email' => strtolower(trim($data['email'])),
            'password' => $temporaryPassword,
            'role' => $legacyRole,
            'profile' => $profile,
            'is_active' => true,
            'is_temporary' => true,
            'must_change_password' => true,
            'profile_setup_complete' => true,
            'tenant_id' => $tenant->id,
            'role_id' => $role->id,
        ]);

        $membership = OrganizationMembership::query()->create([
            'organization_id' => $tenant->id,
            'user_id' => $newUser->id,
            'role_id' => $role->id,
            'status' => 'active',
            'title' => $data['title'] ?? $role->name,
            'invited_by_user_id' => $user->id,
        ]);

        return response()->json([
            'member' => $this->serializeMembership($membership->fresh(['user', 'role.permissions', 'organization'])),
            'temporaryPassword' => $temporaryPassword,
        ], Response::HTTP_CREATED);
    }

    public function storeRole(Request $request, Organization $tenant)
    {
        /** @var User $user */
        $user = $request->user();

        if (! $this->canManageTenantRoles($user, $tenant)) {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $role = $this->tenantRoleService->createRole(
            $tenant,
            $data['name'],
            $data['slug'] ?? null,
            $data['description'] ?? null,
        );

        return response()->json([
            'role' => $this->serializeRole($role->load(['permissions', 'memberships'])),
        ], Response::HTTP_CREATED);
    }

    public function destroyRole(Request $request, Organization $tenant, Role $role)
    {
        /** @var User $user */
        $user = $request->user();

        if (! $this->canManageTenantRoles($user, $tenant)) {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        if ((int) $role->tenant_id !== (int) $tenant->id) {
            return response()->json(['message' => 'Role does not belong to the selected tenant.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($role->memberships()->exists()) {
            return response()->json(['message' => 'Role is assigned to users and cannot be deleted yet.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $role->delete();

        return response()->json(['message' => 'Role deleted.']);
    }

    public function syncPermissions(Request $request, Organization $tenant, Role $role)
    {
        /** @var User $user */
        $user = $request->user();

        if (! $this->canManageTenantRoles($user, $tenant)) {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        if ((int) $role->tenant_id !== (int) $tenant->id) {
            return response()->json(['message' => 'Role does not belong to the selected tenant.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $data = $request->validate([
            'permissions' => ['array'],
            'permissions.*' => ['string', 'exists:permissions,key'],
        ]);

        $this->tenantRoleService->syncPermissions($role, $data['permissions'] ?? []);

        return response()->json([
            'role' => $this->serializeRole($role->fresh(['permissions', 'memberships'])),
        ]);
    }

    private function canManageTenantRoles(User $user, Organization $tenant): bool
    {
        if ($this->isSystemAdmin($user)) {
            return true;
        }

        $membership = OrganizationMembership::query()
            ->select(['id', 'organization_id', 'user_id', 'role_id', 'status'])
            ->where('user_id', $user->id)
            ->where('organization_id', $tenant->id)
            ->where('status', 'active')
            ->with(['role:id,name,slug', 'role.permissions:id,key'])
            ->first();

        if (! $membership) {
            return false;
        }

        $permissions = $membership->effectivePermissions();

        return in_array('manage_roles', $permissions, true)
            || in_array('manage_permissions', $permissions, true)
            || in_array('org.manage_roles', $permissions, true);
    }

    private function canManageTenantMembers(User $user, Organization $tenant): bool
    {
        if ($this->isSystemAdmin($user)) {
            return true;
        }

        $membership = OrganizationMembership::query()
            ->select(['id', 'organization_id', 'user_id', 'role_id', 'status'])
            ->where('user_id', $user->id)
            ->where('organization_id', $tenant->id)
            ->where('status', 'active')
            ->with(['role:id,name,slug', 'role.permissions:id,key'])
            ->first();

        if (! $membership) {
            return false;
        }

        $permissions = $membership->effectivePermissions();

        return in_array('manage_users', $permissions, true)
            || in_array('org.manage_members', $permissions, true)
            || in_array('org.manage_roles', $permissions, true);
    }

    private function isSystemAdmin(User $user): bool
    {
        return $user->role === 'admin'
            || $user->platformRole?->slug === 'system_admin'
            || in_array('platform.manage_users', $user->platformPermissionKeys(), true);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeTenant(Organization $tenant): array
    {
        return [
            'id' => (string) $tenant->id,
            'name' => $tenant->name,
            'type' => $tenant->type,
            'memberCount' => (int) ($tenant->memberships_count ?? 0),
            'roleCount' => (int) ($tenant->roles_count ?? 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeRole(Role $role): array
    {
        return [
            'id' => (string) $role->id,
            'tenantId' => $role->tenant_id ? (string) $role->tenant_id : null,
            'name' => $role->name,
            'slug' => $role->slug,
            'description' => $role->description,
            'memberCount' => (int) ($role->memberships_count ?? 0),
            'permissions' => $role->permissions->pluck('key')->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeMembership(OrganizationMembership $membership): array
    {
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
            'role' => [
                'id' => (string) $membership->role->id,
                'name' => $membership->role->name,
                'slug' => $membership->role->slug,
                'permissions' => $membership->role->permissions->pluck('key')->values()->all(),
            ],
        ];
    }
}
