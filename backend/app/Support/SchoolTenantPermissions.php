<?php

namespace App\Support;

use App\Models\OrganizationMembership;
use App\Models\User;

final class SchoolTenantPermissions
{
    /**
     * Permissions that imply the member may run school coordination (roster, endorsements, school postings).
     *
     * @return array<int, string>
     */
    public static function coordinationPermissionKeys(): array
    {
        return [
            'manage_users',
            'org.manage_members',
            'manage_roles',
            'manage_permissions',
            'org.manage_roles',
            'manage_contracts',
            'org.manage_contracts',
            'manage_subscription',
            'org.manage_subscription',
        ];
    }

    /**
     * @param  array<int, string>  $permissions
     */
    public static function hasCoordinationPermission(array $permissions): bool
    {
        foreach (self::coordinationPermissionKeys() as $key) {
            if (in_array($key, $permissions, true)) {
                return true;
            }
        }

        return false;
    }

    public static function userMayCoordinateSchoolTenant(User $user): bool
    {
        $org = $user->activeOrganization();
        if (! $org || $org->type !== 'school') {
            return false;
        }

        if ((int) ($org->owner_user_id ?? 0) === (int) $user->id) {
            return true;
        }

        $membership = $user->primaryOrganizationMembership();
        if (! $membership || (int) $membership->organization_id !== (int) $org->id) {
            return false;
        }

        $membership->loadMissing('role.permissions');

        return self::hasCoordinationPermission($membership->effectivePermissions());
    }

    /**
     * @param  array<int, string>  $permissions
     */
    public static function hasContractPermission(array $permissions): bool
    {
        return in_array('manage_contracts', $permissions, true)
            || in_array('org.manage_contracts', $permissions, true);
    }

    public static function userMayManageSchoolContracts(User $user): bool
    {
        $org = $user->activeOrganization();
        if (! $org || $org->type !== 'school') {
            return false;
        }

        if ((int) ($org->owner_user_id ?? 0) === (int) $user->id) {
            return true;
        }

        $membership = $user->primaryOrganizationMembership();
        if (! $membership) {
            return false;
        }

        $membership->loadMissing('role.permissions');
        $permissions = $membership->effectivePermissions();

        return self::hasContractPermission($permissions)
            || self::hasCoordinationPermission($permissions);
    }

    public static function activeSchoolMembership(User $user): ?OrganizationMembership
    {
        $org = $user->activeOrganization();
        if (! $org || $org->type !== 'school') {
            return null;
        }

        $membership = $user->primaryOrganizationMembership();
        if (! $membership || (int) $membership->organization_id !== (int) $org->id) {
            return null;
        }

        return $membership;
    }
}
