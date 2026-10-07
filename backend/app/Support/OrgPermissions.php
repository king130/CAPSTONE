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
            'org.manage_profile',
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
