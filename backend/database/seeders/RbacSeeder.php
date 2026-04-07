<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RbacSeeder extends Seeder
{
    public function run(): void
    {
        $permissionMap = [
            'manage_users' => ['scope' => 'organization', 'description' => 'Manage users inside a tenant'],
            'manage_roles' => ['scope' => 'organization', 'description' => 'Create, edit, and delete tenant roles'],
            'manage_permissions' => ['scope' => 'organization', 'description' => 'Assign permissions to tenant roles'],
            'view_reports' => ['scope' => 'organization', 'description' => 'View tenant reports'],
            'upload_files' => ['scope' => 'organization', 'description' => 'Upload files and documents'],
            'manage_subscription' => ['scope' => 'organization', 'description' => 'Manage tenant subscription settings'],
            'manage_profile' => ['scope' => 'organization', 'description' => 'Update tenant profile details'],
            'manage_internships' => ['scope' => 'organization', 'description' => 'Create and manage internships'],
            'manage_contracts' => ['scope' => 'organization', 'description' => 'Create and manage contracts'],
            'review_applications' => ['scope' => 'organization', 'description' => 'Review incoming applications'],
            'platform.manage_users' => ['scope' => 'platform', 'description' => 'Manage all users across the platform'],
            'platform.manage_subscriptions' => ['scope' => 'platform', 'description' => 'Approve and manage subscriptions'],
            'platform.manage_pricing' => ['scope' => 'platform', 'description' => 'Manage subscription pricing'],
            'platform.view_reports' => ['scope' => 'platform', 'description' => 'View platform-wide reports'],
            'org.manage_members' => ['scope' => 'organization', 'description' => 'Invite and manage organization members'],
            'org.manage_roles' => ['scope' => 'organization', 'description' => 'Toggle role access inside the organization'],
            'org.manage_subscription' => ['scope' => 'organization', 'description' => 'Manage organization subscription'],
            'org.manage_profile' => ['scope' => 'organization', 'description' => 'Update organization profile'],
            'org.manage_internships' => ['scope' => 'organization', 'description' => 'Create and update internships'],
            'org.manage_contracts' => ['scope' => 'organization', 'description' => 'Create and manage contracts'],
            'org.review_applications' => ['scope' => 'organization', 'description' => 'Review internship applications'],
            'org.view_reports' => ['scope' => 'organization', 'description' => 'View organization reports'],
        ];

        foreach ($permissionMap as $key => $meta) {
            Permission::query()->updateOrCreate(
                ['key' => $key],
                ['scope' => $meta['scope'], 'description' => $meta['description']]
            );
        }

        $roles = [
            'system_admin' => [
                'name' => 'System Admin',
                'scope' => 'platform',
                'organization_type' => null,
                'permissions' => [
                    'platform.manage_users',
                    'platform.manage_subscriptions',
                    'platform.manage_pricing',
                    'platform.view_reports',
                ],
            ],
            'platform_super_admin' => [
                'name' => 'Platform Super Admin',
                'scope' => 'platform',
                'organization_type' => null,
                'permissions' => [
                    'platform.manage_users',
                    'platform.manage_subscriptions',
                    'platform.manage_pricing',
                    'platform.view_reports',
                ],
            ],
            'platform_support_admin' => [
                'name' => 'Platform Support Admin',
                'scope' => 'platform',
                'organization_type' => null,
                'permissions' => [
                    'platform.manage_users',
                    'platform.manage_subscriptions',
                    'platform.view_reports',
                ],
            ],
            'school_admin' => [
                'name' => 'School Admin',
                'scope' => 'organization',
                'organization_type' => 'school',
                'permissions' => [
                    'org.manage_members',
                    'org.manage_roles',
                    'org.manage_subscription',
                    'org.manage_profile',
                    'org.manage_contracts',
                    'org.view_reports',
                ],
            ],
            'school_department_head' => [
                'name' => 'School Department Head',
                'scope' => 'organization',
                'organization_type' => 'school',
                'permissions' => [
                    'org.manage_profile',
                    'org.manage_contracts',
                    'org.view_reports',
                ],
            ],
            'company_admin' => [
                'name' => 'Company Admin',
                'scope' => 'organization',
                'organization_type' => 'company',
                'permissions' => [
                    'org.manage_members',
                    'org.manage_roles',
                    'org.manage_subscription',
                    'org.manage_profile',
                    'org.manage_internships',
                    'org.manage_contracts',
                    'org.review_applications',
                    'org.view_reports',
                ],
            ],
            'company_hr_manager' => [
                'name' => 'Company HR Manager',
                'scope' => 'organization',
                'organization_type' => 'company',
                'permissions' => [
                    'org.manage_internships',
                    'org.manage_contracts',
                    'org.review_applications',
                    'org.view_reports',
                ],
            ],
            'student_member' => [
                'name' => 'Student Member',
                'scope' => 'organization',
                'organization_type' => 'school',
                'permissions' => [],
            ],
        ];

        foreach ($roles as $slug => $meta) {
            $role = Role::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $meta['name'],
                    'scope' => $meta['scope'],
                    'organization_type' => $meta['organization_type'],
                    'description' => $meta['name'],
                    'is_system' => true,
                ]
            );

            $permissionIds = Permission::query()
                ->whereIn('key', $meta['permissions'])
                ->pluck('id')
                ->all();

            $role->permissions()->sync($permissionIds);
        }
    }
}
