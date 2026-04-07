<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\School;

class Internship extends Model
{
    protected $fillable = [
        'company_id',
        'school_id',
        'company_name',
        'industry',
        'host_type',
        'host_name',
        'title',
        'description',
        'location',
        'type',
        'duration',
        'slots_available',
        'status',
        'requirements',
        'eligible_courses',
        'allowance',
        'start_date',
        'end_date',
        'schedule',
        'tasks',
        'required_skills',
        'intern_gains',
        'required_documents',
        'application_instructions',
        'contact_info',
        'approval_status',
        'approval_notes',
    ];

    protected $casts = [
        'requirements' => 'array',
        'eligible_courses' => 'array',
        'required_skills' => 'array',
        'required_documents' => 'array',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
