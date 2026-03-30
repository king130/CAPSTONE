<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationMembership extends Model
{
    protected $fillable = [
        'organization_id',
        'user_id',
        'role_id',
        'status',
        'title',
        'permissions_override',
        'invited_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'permissions_override' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    /**
     * @return array<int, string>
     */
    public function effectivePermissions(): array
    {
        $base = $this->role?->permissions->pluck('key')->all() ?? [];
        $override = is_array($this->permissions_override) ? $this->permissions_override : [];

        $grant = collect($override['grant'] ?? [])->filter()->values()->all();
        $deny = collect($override['deny'] ?? [])->filter()->values()->all();

        return array_values(array_unique(array_diff(array_merge($base, $grant), $deny)));
    }
}
