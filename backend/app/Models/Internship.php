<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        'host_required_hours',
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
        'schedule_slots',
        'timezone',
        'is_flexible',
        'tasks',
        'required_skills',
        'intern_gains',
        'required_documents',
        'requires_resume',
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
        'schedule_slots' => 'array',
        'perks' => 'array',
        'weekly_hours' => 'decimal:2',
        'host_required_hours' => 'integer',
        'is_flexible' => 'boolean',
        'requires_resume' => 'boolean',
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

    public function catalogSkills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class, 'internship_skill')
            ->withPivot(['importance'])
            ->withTimestamps()
            ->orderBy('skills.name');
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
