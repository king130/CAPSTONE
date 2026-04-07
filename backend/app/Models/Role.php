<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'scope',
        'organization_type',
        'description',
        'is_system',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions')->withTimestamps();
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'tenant_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(OrganizationMembership::class);
    }

    public function platformUsers(): HasMany
    {
        return $this->hasMany(User::class, 'platform_role_id');
    }

    public function directUsers(): HasMany
    {
        return $this->hasMany(User::class, 'role_id');
    }
}
