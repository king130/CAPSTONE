<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Organization extends Model
{
    protected $fillable = [
        'name',
        'type',
        'subscription_id',
        'owner_user_id',
        'settings',
        'is_active',
        'tagline',
        'description',
        'address',
        'city',
        'website',
        'industry',
        'perks',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'perks' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(OrganizationMembership::class);
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class, 'tenant_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_memberships')
            ->withPivot(['role_id', 'status', 'title', 'permissions_override', 'invited_by_user_id'])
            ->withTimestamps();
    }

    public function contractTypes(): HasMany
    {
        return $this->hasMany(ContractType::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(OrganizationMedia::class)->orderBy('sort_order')->orderBy('id');
    }

    public function coverMedia(): HasMany
    {
        return $this->hasMany(OrganizationMedia::class)->where('is_cover', true);
    }

    public function companyProfile(): HasOne
    {
        return $this->hasOne(Company::class);
    }

    public function schoolProfile(): HasOne
    {
        return $this->hasOne(School::class);
    }

    public function hostedInternships(): HasManyThrough
    {
        return $this->hasManyThrough(
            Internship::class,
            Company::class,
            'organization_id',
            'company_id',
            'id',
            'id',
        );
    }
}
