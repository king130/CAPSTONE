<?php

namespace App\Support;

use App\Models\User;

final class TenantSubscriptionPermissions
{
    /**
     * @param  array<int, string>  $permissions
     */
    public static function hasSubscriptionPermission(array $permissions): bool
    {
        return in_array('manage_subscription', $permissions, true)
            || in_array('org.manage_subscription', $permissions, true);
    }

    public static function userMayManageOrganizationSubscription(User $user): bool
    {
        $organization = $user->activeOrganization();
        if (! $organization) {
            return false;
        }

        if ((int) ($organization->owner_user_id ?? 0) === (int) $user->id) {
            return true;
        }

        $membership = $user->primaryOrganizationMembership();
        if (! $membership || (int) $membership->organization_id !== (int) $organization->id) {
            return false;
        }

        $membership->loadMissing('role.permissions');

        return self::hasSubscriptionPermission($membership->effectivePermissions());
    }
}
