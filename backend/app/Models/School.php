<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class School extends Model
{
    protected $fillable = [
        'user_id',
        'subscription_id',
        'institution_name',
        'institution_type',
        'department',
        'position',
        'official_school_email',
        'school_contact_number',
        'school_address',
        'school_id_number',
        'ched_deped_number',
        'school_accreditation',
        'registrar_name',
        'registrar_email',
        'subscription_code',
        'verification_status',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
