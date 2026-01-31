<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Internship extends Model
{
    protected $fillable = [
        'company_id',
        'title',
        'description',
        'location',
        'type',
        'duration',
        'slots_available',
        'status',
        'requirements',
    ];

    protected $casts = [
        'requirements' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
