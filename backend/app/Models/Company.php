<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Company extends Model
{
    protected $fillable = [
        'user_id',
        'subscription_id',
        'organization_id',
        'company_name',
        'company_type',
        'lgu_type',
        'industry_type',
        'company_address',
        'city_municipality',
        'company_email',
        'contact_person_name',
        'contact_person_title',
        'contact_person_email',
        'company_contact_number',
        'website',
        'internship_description',
        'business_reg_number',
        'tax_identification_number',
        'verification_status',
    ];

    public function isMunicipalHall(): bool
    {
        return $this->organization?->type === 'municipal_hall'
            || ($this->lgu_type !== null && $this->lgu_type !== '');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
