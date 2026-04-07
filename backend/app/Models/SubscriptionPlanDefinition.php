<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionPlanDefinition extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'description',
        'school_price',
        'company_price',
        'school_features',
        'company_features',
        'school_coordinators_limit',
        'school_students_limit',
        'company_accounts_limit',
        'company_internships_limit',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'school_features' => 'array',
            'company_features' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
