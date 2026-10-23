<?php

namespace App\Support;

/**
 * Canonical organization permission catalog (View / Manage / Reports / Approve).
 */
class OrgPermissions
{
    /**
     * @return array<string, array{scope:string, description:string}>
     */
    public static function catalog(): array
    {
        return [
            // Legacy (kept for compatibility)
            'manage_users' => ['scope' => 'organization', 'description' => 'Manage users inside a tenant'],
            'manage_roles' => ['scope' => 'organization', 'description' => 'Create, edit, and delete tenant roles'],
            'manage_permissions' => ['scope' => 'organization', 'description' => 'Assign permissions to tenant roles'],
            'view_reports' => ['scope' => 'organization', 'description' => 'View tenant reports'],
            'upload_files' => ['scope' => 'organization', 'description' => 'Upload files and documents'],
            'manage_subscription' => ['scope' => 'organization', 'description' => 'Manage tenant subscription settings'],
            'manage_profile' => ['scope' => 'organization', 'description' => 'Update tenant profile details'],
            'manage_internships' => ['scope' => 'organization', 'description' => 'Create and manage internships'],
            'manage_contracts' => ['scope' => 'organization', 'description' => 'Create and manage agreements (legacy key)'],
            'review_applications' => ['scope' => 'organization', 'description' => 'Review incoming applications'],

            // Platform
            'platform.manage_users' => ['scope' => 'platform', 'description' => 'Manage all users across the platform'],
            'platform.manage_subscriptions' => ['scope' => 'platform', 'description' => 'Approve and manage subscriptions'],
            'platform.manage_pricing' => ['scope' => 'platform', 'description' => 'Manage subscription pricing'],
            'platform.view_reports' => ['scope' => 'platform', 'description' => 'View platform-wide reports'],

            // Org admin
            'org.manage_members' => ['scope' => 'organization', 'description' => 'Invite and manage organization members'],
            'org.manage_roles' => ['scope' => 'organization', 'description' => 'Toggle role access inside the organization'],
            'org.manage_subscription' => ['scope' => 'organization', 'description' => 'Manage organization subscription'],
            'org.manage_profile' => ['scope' => 'organization', 'description' => 'Update organization profile'],

            // View
            'org.view_internships' => ['scope' => 'organization', 'description' => 'View internships'],
            'org.view_agreements' => ['scope' => 'organization', 'description' => 'View agreements'],
            'org.view_applications' => ['scope' => 'organization', 'description' => 'View applications'],
            'org.view_students' => ['scope' => 'organization', 'description' => 'View student roster and progress'],
            'org.view_ojt_progress' => ['scope' => 'organization', 'description' => 'Monitor student OJT progress'],
            'org.view_reports' => ['scope' => 'organization', 'description' => 'View organization reports'],

            // Manage
            'org.manage_internships' => ['scope' => 'organization', 'description' => 'Create and update internships'],
            'org.manage_agreements' => ['scope' => 'organization', 'description' => 'Create and manage agreements'],
            'org.manage_contracts' => ['scope' => 'organization', 'description' => 'Create and manage agreements (legacy alias)'],
            'org.manage_applications' => ['scope' => 'organization', 'description' => 'Manage internship applications'],
            'org.manage_students' => ['scope' => 'organization', 'description' => 'Manage student accounts'],
            'org.manage_assessments' => ['scope' => 'organization', 'description' => 'Create and edit OJT application assessments'],

            // Reports
            'org.reports_view' => ['scope' => 'organization', 'description' => 'View reports and KPI dashboards'],
            'org.reports_export' => ['scope' => 'organization', 'description' => 'Export reports'],

            // Approve
            'org.approve_applications' => ['scope' => 'organization', 'description' => 'Endorse or decide on applications'],
            'org.approve_agreements' => ['scope' => 'organization', 'description' => 'Accept or approve agreement changes'],
            'org.approve_ojt_logs' => ['scope' => 'organization', 'description' => 'Approve or reject OJT hour logs'],
            'org.approve_certificates' => ['scope' => 'organization', 'description' => 'Issue certificates of completion'],

            // Legacy review key
            'org.review_applications' => ['scope' => 'organization', 'description' => 'Review internship applications (legacy)'],
        ];
    }

    /**
     * Organization-scoped permission keys from the canonical catalog.
     *
     * @return array<int, string>
     */
    public static function organizationPermissionKeys(): array
    {
        return array_values(array_keys(array_filter(
            self::catalog(),
            static fn (array $meta): bool => ($meta['scope'] ?? null) === 'organization'
        )));
    }

    /**
     * Keys shown as separate toggles in the RBAC matrix.
     * Legacy aliases stay in the catalog and PermissionGate, but are not listed twice.
     *
     * @return array<int, string>
     */
    public static function assignableOrganizationPermissionKeys(): array
    {
        return [
            'org.manage_members',
            'org.manage_roles',
            'org.manage_subscription',
            'org.manage_profile',
            'org.view_internships',
            'org.view_agreements',
            'org.view_applications',
            'org.view_students',
            'org.view_ojt_progress',
            'org.view_reports',
            'org.manage_internships',
            'org.manage_agreements',
            'org.manage_applications',
            'org.manage_students',
            'org.manage_assessments',
            'org.reports_export',
            'org.approve_applications',
            'org.approve_agreements',
            'org.approve_ojt_logs',
            'org.approve_certificates',
            'upload_files',
        ];
    }

    /**
     * Assignable key → every stored key that means the same capability.
     * Toggling the assignable key on/off should write or clear the whole group.
     *
     * @return array<string, array<int, string>>
     */
    public static function assignableAliasGroups(): array
    {
        return [
            'org.manage_members' => ['org.manage_members', 'manage_users'],
            'org.manage_roles' => ['org.manage_roles', 'manage_roles', 'manage_permissions'],
            'org.manage_subscription' => ['org.manage_subscription', 'manage_subscription'],
            'org.manage_profile' => ['org.manage_profile', 'manage_profile'],
            'org.view_internships' => ['org.view_internships'],
            'org.view_agreements' => ['org.view_agreements'],
            'org.view_applications' => ['org.view_applications'],
            'org.view_students' => ['org.view_students'],
            'org.view_ojt_progress' => ['org.view_ojt_progress'],
            'org.view_reports' => ['org.view_reports', 'org.reports_view', 'view_reports'],
            'org.manage_internships' => ['org.manage_internships', 'manage_internships'],
            'org.manage_agreements' => ['org.manage_agreements', 'org.manage_contracts', 'manage_contracts'],
            'org.manage_applications' => ['org.manage_applications'],
            'org.manage_students' => ['org.manage_students'],
            'org.manage_assessments' => ['org.manage_assessments'],
            'org.reports_export' => ['org.reports_export'],
            'org.approve_applications' => ['org.approve_applications', 'org.review_applications', 'review_applications'],
            'org.approve_agreements' => ['org.approve_agreements'],
            'org.approve_ojt_logs' => ['org.approve_ojt_logs'],
            'org.approve_certificates' => ['org.approve_certificates'],
            'upload_files' => ['upload_files'],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function aliasGroupFor(string $permissionKey): array
    {
        $groups = self::assignableAliasGroups();
        if (isset($groups[$permissionKey])) {
            return $groups[$permissionKey];
        }

        foreach ($groups as $aliases) {
            if (in_array($permissionKey, $aliases, true)) {
                return $aliases;
            }
        }

        return [$permissionKey];
    }

    /**
     * @return array<int, string>
     */
    public static function schoolAdmin(): array
    {
        return [
            'org.manage_members',
            'org.manage_roles',
            'org.manage_subscription',
            'org.manage_profile',
            'org.view_internships',
            'org.manage_internships',
            'org.view_agreements',
            'org.manage_agreements',
            'org.approve_agreements',
            'org.view_applications',
            'org.manage_applications',
            'org.approve_applications',
            'org.view_students',
            'org.manage_students',
            'org.view_ojt_progress',
            'org.approve_ojt_logs',
            'org.manage_assessments',
            'org.view_reports',
            'org.reports_view',
            'org.reports_export',
            'org.approve_certificates',
            // legacy
            'manage_users',
            'manage_roles',
            'manage_permissions',
            'view_reports',
            'upload_files',
            'manage_profile',
            'manage_subscription',
            'manage_contracts',
            'org.manage_contracts',
            'org.review_applications',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function schoolDepartmentHead(): array
    {
        return [
            'org.view_internships',
            'org.view_agreements',
            'org.manage_agreements',
            'org.approve_agreements',
            'org.view_applications',
            'org.approve_applications',
            'org.view_students',
            'org.view_ojt_progress',
            'org.approve_ojt_logs',
            'org.manage_assessments',
            'org.view_reports',
            'org.reports_view',
            'org.reports_export',
            'org.approve_certificates',
            'view_reports',
            'org.manage_contracts',
            'org.review_applications',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function companyAdmin(): array
    {
        return [
            'org.manage_members',
            'org.manage_roles',
            'org.manage_subscription',
            'org.manage_profile',
            'org.view_internships',
            'org.manage_internships',
            'org.view_agreements',
            'org.manage_agreements',
            'org.approve_agreements',
            'org.view_applications',
            'org.manage_applications',
            'org.approve_applications',
            'org.approve_ojt_logs',
            'org.manage_assessments',
            'org.view_reports',
            'org.reports_view',
            'org.reports_export',
            'org.approve_certificates',
            'manage_users',
            'manage_roles',
            'manage_permissions',
            'view_reports',
            'upload_files',
            'manage_profile',
            'manage_subscription',
            'manage_internships',
            'manage_contracts',
            'review_applications',
            'org.manage_contracts',
            'org.review_applications',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function companyHr(): array
    {
        return [
            'org.view_internships',
            'org.manage_internships',
            'org.view_agreements',
            'org.manage_agreements',
            'org.approve_agreements',
            'org.view_applications',
            'org.manage_applications',
            'org.approve_applications',
            'org.approve_ojt_logs',
            'org.manage_assessments',
            'org.view_reports',
            'org.reports_view',
            'manage_internships',
            'manage_contracts',
            'review_applications',
            'view_reports',
            'org.manage_contracts',
            'org.review_applications',
        ];
    }
}
