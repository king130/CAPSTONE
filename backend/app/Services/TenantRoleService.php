<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Support\OrgPermissions;
use Illuminate\Support\Str;

class TenantRoleService
{
    /**
     * @return array<int, Role>
     */
    public function ensureDefaultRoles(Organization $tenant): array
    {
        $tenant->refresh();

        $existing = Role::query()
            ->where('tenant_id', $tenant->id)
            ->count();

        if ($existing > 0) {
            return Role::query()
                ->where('tenant_id', $tenant->id)
                ->with('permissions')
                ->orderBy('name')
                ->get()
                ->all();
        }

        $roles = [];
        foreach ($this->defaultDefinitionsForType($tenant->type) as $definition) {
            $role = Role::query()->create([
                'tenant_id' => $tenant->id,
                'name' => $definition['name'],
                'slug' => $definition['slug'],
                'scope' => 'organization',
                'organization_type' => $tenant->type,
                'description' => $definition['description'],
                'is_system' => false,
            ]);

            $this->syncPermissions($role, $definition['permissions']);
            $roles[] = $role->load('permissions');
        }

        return $roles;
    }

    public function createRole(Organization $tenant, string $name, ?string $slug = null, ?string $description = null): Role
    {
        $baseSlug = Str::slug($slug ?: $name, '_');
        $resolvedSlug = $this->resolveUniqueSlug($tenant, $baseSlug ?: 'role');

        return Role::query()->create([
            'tenant_id' => $tenant->id,
            'name' => trim($name),
            'slug' => $resolvedSlug,
            'scope' => 'organization',
            'organization_type' => $tenant->type,
            'description' => $description ? trim($description) : null,
            'is_system' => false,
        ]);
    }

    /**
     * @param array<int, string> $keys
     */
    public function syncPermissions(Role $role, array $keys): void
    {
        $permissionIds = Permission::query()
            ->whereIn('key', collect($keys)->filter()->unique()->values()->all())
            ->pluck('id')
            ->all();

        $role->permissions()->sync($permissionIds);
    }

    /**
     * @return array<int, array{name:string,slug:string,description:string,permissions:array<int,string>}>
     */
    public function defaultDefinitionsForType(string $type): array
    {
        if ($type === 'school') {
            return [
                [
                    'name' => 'School Admin',
                    'slug' => 'school_admin',
                    'description' => 'Full administrative access inside this school tenant.',
                    'permissions' => OrgPermissions::schoolAdmin(),
                ],
                [
                    'name' => 'Department Head',
                    'slug' => 'department_head',
                    'description' => 'Monitors student progress, reports, and application approvals.',
                    'permissions' => OrgPermissions::schoolDepartmentHead(),
                ],
                [
                    'name' => 'Teacher',
                    'slug' => 'teacher',
                    'description' => 'Coordinates students and academic workflows.',
                    'permissions' => [
                        'org.view_students',
                        'org.view_ojt_progress',
                        'org.view_applications',
                        'org.view_reports',
                        'org.reports_view',
                        'view_reports',
                        'upload_files',
                        'manage_profile',
                    ],
                ],
                [
                    'name' => 'Registrar',
                    'slug' => 'registrar',
                    'description' => 'Handles school records and operational files.',
                    'permissions' => [
                        'org.view_students',
                        'org.manage_students',
                        'org.view_reports',
                        'org.reports_view',
                        'manage_users',
                        'upload_files',
                        'view_reports',
                    ],
                ],
                [
                    'name' => 'Intern',
                    'slug' => 'intern',
                    'description' => 'Student intern role for school-managed tenant access.',
                    'permissions' => [
                        'upload_files',
                    ],
                ],
            ];
        }

        return [
            [
                'name' => 'Company Admin',
                'slug' => 'company_admin',
                'description' => 'Full administrative access inside this company tenant.',
                'permissions' => OrgPermissions::companyAdmin(),
            ],
            [
                'name' => 'Manager',
                'slug' => 'manager',
                'description' => 'Manages team operations and reporting.',
                'permissions' => [
                    'org.view_internships',
                    'org.manage_internships',
                    'org.view_applications',
                    'org.approve_applications',
                    'org.view_reports',
                    'org.reports_view',
                    'view_reports',
                    'manage_internships',
                    'review_applications',
                ],
            ],
            [
                'name' => 'HR',
                'slug' => 'hr',
                'description' => 'Handles users, applicants, and onboarding documents.',
                'permissions' => OrgPermissions::companyHr(),
            ],
            [
                'name' => 'Finance',
                'slug' => 'finance',
                'description' => 'Reviews subscription and agreement-related work.',
                'permissions' => [
                    'org.view_agreements',
                    'org.manage_agreements',
                    'org.view_reports',
                    'org.reports_view',
                    'view_reports',
                    'manage_subscription',
                    'manage_contracts',
                    'org.manage_subscription',
                ],
            ],
        ];
    }

    private function resolveUniqueSlug(Organization $tenant, string $baseSlug): string
    {
        $slug = $baseSlug;
        $index = 2;

        while (
            Role::query()
                ->where('tenant_id', $tenant->id)
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = "{$baseSlug}_{$index}";
            $index++;
        }

        return $slug;
    }
}
