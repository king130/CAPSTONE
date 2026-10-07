<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'city',
        'work_setup',
        'type',
        'duration',
        'slots_available',
        'status',
        'requirements',
        'eligible_courses',
        'allowance',
        'perks',
        'start_date',
        'end_date',
        'application_deadline',
        'schedule',
        'schedule_type',
        'weekly_hours',
        'schedule_days',
        'time_in',
        'time_out',
        'timezone',
        'is_flexible',
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
        'schedule_days' => 'array',
        'perks' => 'array',
        'weekly_hours' => 'decimal:2',
        'is_flexible' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
        'application_deadline' => 'date',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    public function savedInternships(): HasMany
    {
        return $this->hasMany(SavedInternship::class);
    }
}
