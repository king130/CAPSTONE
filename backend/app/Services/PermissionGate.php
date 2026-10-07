<?php

namespace App\Services;

use App\Models\OrganizationMembership;
use App\Models\User;

class PermissionGate
{
    /**
     * Legacy permission keys mapped to the canonical View/Manage/Reports/Approve set.
     *
     * @var array<string, array<int, string>>
     */
    public const ALIASES = [
        'org.view_internships' => ['manage_internships', 'org.manage_internships'],
        'org.manage_internships' => ['manage_internships'],
        'org.view_agreements' => ['manage_contracts', 'org.manage_contracts', 'org.manage_agreements', 'org.view_contracts'],
        'org.manage_agreements' => ['manage_contracts', 'org.manage_contracts', 'org.manage_agreements'],
        'org.approve_agreements' => ['manage_contracts', 'org.manage_contracts', 'org.manage_agreements', 'org.approve_agreements'],
        'org.view_applications' => ['review_applications', 'org.review_applications', 'org.approve_applications'],
        'org.manage_applications' => ['review_applications', 'org.review_applications'],
        'org.approve_applications' => ['review_applications', 'org.review_applications', 'org.approve_applications'],
        'org.view_students' => ['manage_users', 'org.manage_members', 'org.view_students'],
        'org.manage_students' => ['manage_users', 'org.manage_members'],
        'org.view_reports' => ['view_reports', 'org.view_reports', 'org.reports_view'],
        'org.reports_view' => ['view_reports', 'org.view_reports', 'org.reports_view'],
        'org.reports_export' => ['view_reports', 'org.view_reports', 'org.reports_view', 'org.reports_export'],
        'org.view_ojt_progress' => ['view_reports', 'org.view_reports', 'org.view_ojt_progress', 'org.view_students'],
        'org.approve_ojt_logs' => ['review_applications', 'org.review_applications', 'org.approve_ojt_logs'],
        'org.manage_assessments' => ['review_applications', 'org.review_applications', 'org.manage_assessments', 'org.approve_applications'],
        'org.approve_certificates' => ['view_reports', 'org.view_reports', 'org.approve_certificates'],
        'org.manage_members' => ['manage_users', 'org.manage_members'],
        'org.manage_roles' => ['manage_roles', 'manage_permissions', 'org.manage_roles'],
        'org.manage_subscription' => ['manage_subscription'],
        // Temporary aliases while contracts terminology still exists server-side
        'org.view_contracts' => ['manage_contracts', 'org.manage_contracts', 'org.view_agreements', 'org.manage_agreements'],
        'org.manage_contracts' => ['manage_contracts', 'org.manage_agreements'],
    ];

    public function userCan(User $user, string $permission): bool
    {
        if ($user->isPlatformAdmin() || $user->effectiveAppRole() === 'admin') {
            return true;
        }

        $membership = $user->primaryOrganizationMembership();
        if (! $membership) {
            return false;
        }

        return $this->membershipCan($membership, $permission);
    }

    public function membershipCan(OrganizationMembership $membership, string $permission): bool
    {
        $effective = $membership->effectivePermissions();

        if (in_array($permission, $effective, true)) {
            return true;
        }

        foreach (self::ALIASES[$permission] ?? [] as $alias) {
            if (in_array($alias, $effective, true)) {
                return true;
            }
        }

        // Also allow canonical key when caller passes a legacy key
        foreach (self::ALIASES as $canonical => $aliases) {
            if (in_array($permission, $aliases, true) && in_array($canonical, $effective, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, string> $permissions
     */
    public function userCanAny(User $user, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->userCan($user, $permission)) {
                return true;
            }
        }

        return false;
    }
}
