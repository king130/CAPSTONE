<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

class Permission extends Model
{
    protected $fillable = [
        'key',
        'scope',
        'description',
    ];

    /**
     * Keys used when the permissions table is empty (migrations/seed not run) so UIs still show toggles.
     *
     * @return array<int, string>
     */
    public static function fallbackOrganizationKeys(): array
    {
        return [
            'manage_users',
            'manage_roles',
            'manage_permissions',
            'view_reports',
            'upload_files',
            'manage_subscription',
            'manage_profile',
            'manage_internships',
            'manage_contracts',
            'review_applications',
            'org.manage_members',
            'org.manage_roles',
            'org.manage_subscription',
            'org.manage_profile',
            'org.manage_internships',
            'org.manage_contracts',
            'org.review_applications',
            'org.view_reports',
        ];
    }

    /**
     * Keys that may be stored on organization memberships or tenant roles (never platform-scoped keys).
     *
     * @return array<int, string>
     */
    public static function assignableOrganizationPermissionKeys(): array
    {
        $fromDb = static::query()
            ->where('scope', 'organization')
            ->pluck('key')
            ->all();

        return array_values(array_unique(array_merge($fromDb, static::fallbackOrganizationKeys())));
    }

    /**
     * Single stored/display key per equivalence group (first entry in each group).
     */
    public static function canonicalOrganizationPermissionKey(string $key): string
    {
        foreach (static::organizationPermissionEquivalenceGroups() as $group) {
            if (in_array($key, $group, true)) {
                return $group[0];
            }
        }

        return $key;
    }

    /**
     * Collapse legacy manage_* / org.* aliases so roles and overrides store one key per capability.
     *
     * @param array<int, string> $keys
     * @return array<int, string>
     */
    public static function normalizeOrganizationPermissionKeys(array $keys): array
    {
        $out = [];
        foreach (array_unique(array_filter($keys)) as $key) {
            $out[] = static::canonicalOrganizationPermissionKey((string) $key);
        }

        return array_values(array_unique($out));
    }

    /**
     * One permission row per logical capability for role-management UIs.
     *
     * @param Collection<int, Permission> $permissionRows
     * @return Collection<int, Permission>
     */
    public static function deduplicateOrganizationPermissionsForUi(Collection $permissionRows): Collection
    {
        $byKey = $permissionRows->keyBy(fn (Permission $p) => $p->key);
        $ordered = collect();

        foreach (static::organizationPermissionEquivalenceGroups() as $group) {
            foreach ($group as $candidate) {
                if ($byKey->has($candidate)) {
                    $ordered->push($byKey->get($candidate));
                    break;
                }
            }
        }

        $inGroup = [];
        foreach (static::organizationPermissionEquivalenceGroups() as $group) {
            foreach ($group as $g) {
                $inGroup[$g] = true;
            }
        }

        foreach ($permissionRows->sortBy('key') as $permission) {
            if (isset($inGroup[$permission->key])) {
                continue;
            }
            $ordered->push($permission);
        }

        return $ordered->values();
    }

    /**
     * Sorted catalog keys for organization access / RBAC pickers (no alias duplicates).
     *
     * @return array<int, string>
     */
    public static function catalogOrganizationPermissionKeysForUi(): array
    {
        $keys = static::query()
            ->where('scope', 'organization')
            ->orderBy('key')
            ->pluck('key')
            ->all();

        if ($keys === []) {
            $keys = static::fallbackOrganizationKeys();
        }

        $normalized = static::normalizeOrganizationPermissionKeys($keys);
        sort($normalized);

        return $normalized;
    }

    /**
     * @deprecated Use assignableOrganizationPermissionKeys()
     *
     * @return array<int, string>
     */
    public static function allowedKeysForOrganizationRoles(): array
    {
        return static::assignableOrganizationPermissionKeys();
    }

    /**
     * Legacy manage_* and org.* pairs are treated as equivalent for subset checks.
     *
     * @return array<int, array<int, string>>
     */
    public static function organizationPermissionEquivalenceGroups(): array
    {
        return [
            ['manage_users', 'org.manage_members'],
            ['manage_roles', 'org.manage_roles', 'manage_permissions'],
            ['manage_subscription', 'org.manage_subscription'],
            ['manage_profile', 'org.manage_profile'],
            ['manage_internships', 'org.manage_internships'],
            ['manage_contracts', 'org.manage_contracts'],
            ['review_applications', 'org.review_applications'],
            ['view_reports', 'org.view_reports'],
            ['upload_files'],
        ];
    }

    /**
     * Whether $actorPerms satisfies a single organization permission (including legacy/org alias pairs).
     *
     * @param array<int, string> $actorPerms
     */
    public static function actorHasOrganizationPermissionKey(array $actorPerms, string $requiredKey): bool
    {
        foreach (static::organizationPermissionEquivalenceGroups() as $group) {
            if (! in_array($requiredKey, $group, true)) {
                continue;
            }

            foreach ($group as $alias) {
                if (in_array($alias, $actorPerms, true)) {
                    return true;
                }
            }

            return false;
        }

        return in_array($requiredKey, $actorPerms, true);
    }

    /**
     * Persist permission rows for keys validated by assignableOrganizationPermissionKeys() before syncing role_permissions.
     *
     * @param array<int, string> $keys
     */
    public static function ensureKeysExistForOrganizationRoles(array $keys): void
    {
        foreach (array_unique(array_filter($keys)) as $key) {
            static::query()->firstOrCreate(
                ['key' => $key],
                ['scope' => 'organization', 'description' => null]
            );
        }
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permissions')->withTimestamps();
    }
}
