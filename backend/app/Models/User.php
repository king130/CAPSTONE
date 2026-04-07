<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'platform_role_id',
        'tenant_id',
        'role_id',
        'profile',
        'is_active',
        'is_temporary',
        'must_change_password',
        'profile_setup_complete',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'profile' => 'array',
            'is_active' => 'boolean',
            'is_temporary' => 'boolean',
            'must_change_password' => 'boolean',
            'profile_setup_complete' => 'boolean',
        ];
    }

    public function student(): HasOne
    {
        return $this->hasOne(Student::class);
    }

    public function company(): HasOne
    {
        return $this->hasOne(Company::class);
    }

    public function school(): HasOne
    {
        return $this->hasOne(School::class);
    }

    public function platformRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'platform_role_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'tenant_id');
    }

    public function assignedRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function organizationMemberships(): HasMany
    {
        return $this->hasMany(OrganizationMembership::class);
    }

    public function isPlatformAdmin(): bool
    {
        return $this->platformRole?->scope === 'platform';
    }

    public function primaryOrganizationMembership(): ?OrganizationMembership
    {
        if (! $this->relationLoaded('organizationMemberships')) {
            $this->load('organizationMemberships.role', 'organizationMemberships.organization.subscription');
        }

        return $this->organizationMemberships
            ->firstWhere('status', 'active');
    }

    public function activeOrganization(): ?Organization
    {
        return $this->primaryOrganizationMembership()?->organization;
    }

    public function organizationType(): ?string
    {
        return $this->activeOrganization()?->type;
    }

    public function organizationCompany(): ?Company
    {
        $organization = $this->activeOrganization();
        if (! $organization || $organization->type !== 'company') {
            return null;
        }

        return Company::query()->where('organization_id', $organization->id)->first();
    }

    public function organizationSchool(): ?School
    {
        $organization = $this->activeOrganization();
        if (! $organization || $organization->type !== 'school') {
            return null;
        }

        return School::query()->where('organization_id', $organization->id)->first();
    }

    public function effectiveAppRole(): ?string
    {
        if ($this->isPlatformAdmin()) {
            return 'admin';
        }

        $membership = $this->primaryOrganizationMembership();
        if ($membership?->organization?->type === 'school') {
            $membershipRole = $membership?->role?->slug;

            if (in_array($membershipRole, ['intern', 'student_member'], true)) {
                return 'student';
            }

            if (! $membershipRole && $this->student) {
                return 'student';
            }

            return 'school';
        }

        if ($membership?->organization?->type === 'company') {
            return 'company';
        }

        return $this->role === 'guest' ? null : $this->role;
    }

    /**
     * @return array<int, string>
     */
    public function platformPermissionKeys(): array
    {
        if (! $this->relationLoaded('platformRole')) {
            $this->load('platformRole.permissions');
        }

        return $this->platformRole?->permissions?->pluck('key')->all() ?? [];
    }

    /**
     * @return Collection<int, OrganizationMembership>
     */
    public function activeMemberships(): Collection
    {
        if (! $this->relationLoaded('organizationMemberships')) {
            $this->load('organizationMemberships.role.permissions', 'organizationMemberships.organization.subscription');
        }

        return $this->organizationMemberships->where('status', 'active')->values();
    }
}
