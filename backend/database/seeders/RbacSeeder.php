<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Support\OrgPermissions;
use Illuminate\Database\Seeder;

class RbacSeeder extends Seeder
{
    public function run(): void
    {
        foreach (OrgPermissions::catalog() as $key => $meta) {
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
                'permissions' => OrgPermissions::schoolAdmin(),
            ],
            'school_department_head' => [
                'name' => 'School Department Head',
                'scope' => 'organization',
                'organization_type' => 'school',
                'permissions' => OrgPermissions::schoolDepartmentHead(),
            ],
            'company_admin' => [
                'name' => 'Company Admin',
                'scope' => 'organization',
                'organization_type' => 'company',
                'permissions' => OrgPermissions::companyAdmin(),
            ],
            'company_hr_manager' => [
                'name' => 'Company HR Manager',
                'scope' => 'organization',
                'organization_type' => 'company',
                'permissions' => OrgPermissions::companyHr(),
            ],
            'student_member' => [
                'name' => 'Student Member',
                'scope' => 'organization',
                'organization_type' => 'school',
                'permissions' => [
                    'upload_files',
                ],
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
