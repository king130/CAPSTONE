<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Company extends Model
{
    protected $fillable = [
        'user_id',
        'subscription_id',
        'company_name',
        'company_type',
        'industry_type',
        'company_address',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
