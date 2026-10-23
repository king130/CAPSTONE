<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\OrganizationAccountProvisioner;
use App\Services\SubscriptionPlanService;
use App\Services\TenantRoleService;
use App\Support\OrgPermissions;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class TenantRbacController extends Controller
{
    public function __construct(
        private readonly TenantRoleService $tenantRoleService,
        private readonly OrganizationAccountProvisioner $organizationAccountProvisioner,
        private readonly SubscriptionPlanService $subscriptionPlans,
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

        $assignableKeys = OrgPermissions::assignableOrganizationPermissionKeys();
        $permissions = Permission::query()
            ->select(['id', 'key', 'description'])
            ->whereIn('key', $assignableKeys)
            ->orderBy('key')
            ->get()
            ->sortBy(fn (Permission $permission) => array_search($permission->key, $assignableKeys, true))
            ->values();

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
                'aliases' => OrgPermissions::aliasGroupFor($permission->key),
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
            'type' => ['required', 'in:company,school,municipal_hall'],
            'name' => ['required', 'string', 'max:255'],
            'adminName' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'plan' => ['nullable', 'string', 'max:255'],
            'billingCycle' => ['nullable', 'string', 'max:255'],
            'lguType' => ['nullable', 'string', 'in:municipality,city,other_office'],
            'locationArea' => ['nullable', 'string', 'max:120'],
            'cityMunicipality' => ['nullable', 'string', 'max:120'],
            'barangay' => ['nullable', 'string', 'max:120'],
            'representativePosition' => ['nullable', 'string', 'max:120'],
            'contactNumber' => ['nullable', 'string', 'max:40'],
        ]);

        if ($data['type'] === 'municipal_hall' && empty($data['locationArea']) && empty($data['cityMunicipality'])) {
            return response()->json([
                'message' => 'A Cavite city or municipality is required for Municipal Hall accounts.',
                'errors' => [
                    'locationArea' => ['A Cavite city or municipality is required for Municipal Hall accounts.'],
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $temporaryPassword = $this->organizationAccountProvisioner->generateTemporaryPassword();
        $city = $data['locationArea'] ?? $data['cityMunicipality'] ?? null;
        $profile = match ($data['type']) {
            'school' => [
                'institutionName' => $data['name'],
                'contactPersonName' => $data['adminName'],
                'position' => 'Primary Admin',
                'officialSchoolEmail' => $data['email'],
            ],
            'municipal_hall' => [
                'officialName' => $data['name'],
                'companyName' => $data['name'],
                'lguType' => $data['lguType'] ?? 'municipality',
                'representativeName' => $data['adminName'],
                'representativePosition' => $data['representativePosition'] ?? 'Primary Admin',
                'contactPersonName' => $data['adminName'],
                'contactPersonTitle' => $data['representativePosition'] ?? 'Primary Admin',
                'officialEmail' => $data['email'],
                'companyEmail' => $data['email'],
                'locationArea' => $city,
                'cityMunicipality' => $city,
                'barangay' => $data['barangay'] ?? null,
                'contactNumber' => $data['contactNumber'] ?? null,
            ],
            default => [
                'companyName' => $data['name'],
                'contactPersonName' => $data['adminName'],
                'contactPersonTitle' => 'Primary Admin',
                'companyEmail' => $data['email'],
            ],
        };

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
            'status' => ['sometimes', 'in:active,inactive,pending'],
        ]);

        $isSelfMembership = (int) $membership->user_id === (int) $user->id;
        if ($isSelfMembership) {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $isOwnerMembership = $tenant->owner_user_id !== null
            && (int) $membership->user_id === (int) $tenant->owner_user_id;
        if ($isOwnerMembership) {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $role = Role::query()
            ->where('id', $data['roleId'])
            ->where('tenant_id', $tenant->id)
            ->first();

        if (! $role) {
            return response()->json(['message' => 'The selected role is not valid for this account.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $membership->role_id = $role->id;
        $membership->title = $membership->title ?: $role->name;

        if (array_key_exists('status', $data)) {
            $membership->status = $data['status'];
            $membership->loadMissing('user');
            $nextStatus = $data['status'];
            $consumesSeat = $this->membershipConsumesAccountSeat($tenant, $role);
            $currentlyConsumesSeat = $membership->status === 'active'
                && $membership->user?->is_active
                && $membership->user?->role === $tenant->type;

            if ($nextStatus === 'active' && $consumesSeat && ! $currentlyConsumesSeat && $this->subscriptionPlans->wouldExceedAfterIncrement($tenant, $this->seatResourceKey($tenant))) {
                return response()->json([
                    'message' => 'Your organization has reached the account limit for the current plan.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $override = is_array($membership->permissions_override) ? $membership->permissions_override : [];
            if ($nextStatus !== 'active') {
                unset($override['planLimitHold']);
                $membership->permissions_override = $override;
            }

            $membership->status = $nextStatus;
        }

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

        if ($this->membershipConsumesAccountSeat($tenant, $role)) {
            $resourceKey = $this->seatResourceKey($tenant);
            if ($this->subscriptionPlans->wouldExceedAfterIncrement($tenant, $resourceKey)) {
                return response()->json([
                    'message' => 'Your organization has reached the account limit for the current plan. Upgrade the subscription before adding another account.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        $temporaryPassword = 'Temp@'.strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        $legacyRole = $tenant->type;
        if ($tenant->type === 'school') {
            $legacyRole = in_array($role->slug, ['intern', 'student_member'], true) ? 'student' : 'school';
        } elseif (\App\Support\OrganizationTypes::isHostType($tenant->type)) {
            // Staff use the company app workspace; organization.type remains municipal_hall when applicable.
            $legacyRole = 'company';
        }

        $profile = $tenant->type === 'school'
            ? [
                'institutionName' => $tenant->name,
                'schoolName' => $tenant->name,
                'position' => $data['title'] ?? $role->name,
            ]
            : (
                $tenant->type === 'municipal_hall'
                    ? [
                        'officialName' => $tenant->name,
                        'companyName' => $tenant->name,
                        'contactPersonTitle' => $data['title'] ?? $role->name,
                        'representativePosition' => $data['title'] ?? $role->name,
                    ]
                    : [
                        'companyName' => $tenant->name,
                        'contactPersonTitle' => $data['title'] ?? $role->name,
                    ]
            );

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

        $organizationPermissionKeys = OrgPermissions::organizationPermissionKeys();

        $data = $request->validate([
            'permissions' => ['array'],
            'permissions.*' => ['string', 'max:255', Rule::in($organizationPermissionKeys)],
        ]);

        $requestedPermissions = array_values(array_unique($data['permissions'] ?? []));

        if (! $this->isSystemAdmin($user)) {
            $actorMembership = $this->activeTenantMembership($user, $tenant);
            if (! $actorMembership) {
                return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
            }

            $actorMembership->loadMissing('role.permissions');
            $actorEffective = $actorMembership->effectivePermissions();

            $role->loadMissing('permissions');
            $currentPermissions = $role->permissions->pluck('key')->all();
            $addedPermissions = array_values(array_diff($requestedPermissions, $currentPermissions));
            $unauthorized = array_values(array_diff($addedPermissions, $actorEffective));

            if ($unauthorized !== []) {
                return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
            }
        }

        $this->tenantRoleService->syncPermissions($role, $requestedPermissions);

        return response()->json([
            'role' => $this->serializeRole($role->fresh(['permissions', 'memberships'])),
        ]);
    }

    private function activeTenantMembership(User $user, Organization $tenant): ?OrganizationMembership
    {
        return OrganizationMembership::query()
            ->select(['id', 'organization_id', 'user_id', 'role_id', 'status', 'permissions_override'])
            ->where('user_id', $user->id)
            ->where('organization_id', $tenant->id)
            ->where('status', 'active')
            ->with(['role:id,name,slug', 'role.permissions:id,key'])
            ->first();
    }

    private function canManageTenantRoles(User $user, Organization $tenant): bool
    {
        if ($this->isSystemAdmin($user)) {
            return true;
        }

        $membership = $this->activeTenantMembership($user, $tenant);

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

        $membership = $this->activeTenantMembership($user, $tenant);

        if (! $membership) {
            return false;
        }

        $permissions = $membership->effectivePermissions();

        return in_array('manage_users', $permissions, true)
            || in_array('org.manage_members', $permissions, true)
            || in_array('org.manage_roles', $permissions, true);
    }

    private function membershipConsumesAccountSeat(Organization $tenant, Role $role): bool
    {
        if (! in_array($tenant->type, ['school', 'company', 'municipal_hall'], true)) {
            return false;
        }

        if ($tenant->type === 'school' && in_array($role->slug, ['intern', 'student_member'], true)) {
            return false;
        }

        return true;
    }

    private function seatResourceKey(Organization $tenant): string
    {
        return $tenant->type === 'school' ? 'school.coordinators' : 'company.accounts';
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
