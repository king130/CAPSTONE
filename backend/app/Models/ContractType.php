<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContractType extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'scope',
        'organization_id',
        'organization_type',
        'created_by_user_id',
        'base_contract_type_id',
        'description',
        'fields_schema',
        'default_values',
        'settings',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'fields_schema' => 'array',
            'default_values' => 'array',
            'settings' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function baseContractType(): BelongsTo
    {
        return $this->belongsTo(self::class, 'base_contract_type_id');
    }

    public function overrides(): HasMany
    {
        return $this->hasMany(self::class, 'base_contract_type_id');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }
}
