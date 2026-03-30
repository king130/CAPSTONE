<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolReport extends Model
{
    protected $fillable = [
        'school_id',
        'organization_id',
        'report_type',
        'student_name',
        'internship_period_start',
        'internship_period_end',
        'company_name',
        'company_status',
        'school_status',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'internship_period_start' => 'date',
            'internship_period_end' => 'date',
            'submitted_at' => 'datetime',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
