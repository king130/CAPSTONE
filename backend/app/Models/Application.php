<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Validation\ValidationException;

class Application extends Model
{
    protected $fillable = [
        'internship_id',
        'student_id',
        'company_id',
        'status',
        'tracker_stage',
        'withdrawal_reason',
        'placement_closed_at',
        'placement_close_reason',
        'active_placement_key',
        'cover_letter',
        'notes',
        'resume_url',
        'documents',
        'documents_pending',
        'internship_title',
        'student_name',
        'student_email',
        'student_course',
        'required_ojt_hours',
    ];

    protected $casts = [
        'documents' => 'array',
        'documents_pending' => 'boolean',
        'required_ojt_hours' => 'integer',
        'placement_closed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $application): void {
            if ($application->required_ojt_hours === null) {
                return;
            }

            if ((int) $application->required_ojt_hours < 1) {
                throw ValidationException::withMessages([
                    'required_ojt_hours' => ['Required OJT hours must be a positive integer when set.'],
                ]);
            }
        });
    }

    public function internship(): BelongsTo
    {
        return $this->belongsTo(Internship::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function interviews(): HasMany
    {
        return $this->hasMany(ApplicationInterview::class);
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(ApplicationAssessment::class);
    }

    public function stageEvents(): HasMany
    {
        return $this->hasMany(ApplicationStageEvent::class);
    }

    public function placementSchedule(): HasOne
    {
        return $this->hasOne(PlacementSchedule::class);
    }

    public function placementScheduleRequests(): HasMany
    {
        return $this->hasMany(PlacementScheduleRequest::class);
    }

    public function placementScheduleVersions(): HasMany
    {
        return $this->hasMany(PlacementScheduleVersion::class);
    }

    public function ojtLogs(): HasMany
    {
        return $this->hasMany(OjtLog::class);
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    public function hoursAgreement(): HasOne
    {
        return $this->hasOne(ApplicationHoursAgreement::class);
    }
}
