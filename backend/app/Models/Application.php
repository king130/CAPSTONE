<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Application extends Model
{
    protected $fillable = [
        'internship_id',
        'student_id',
        'company_id',
        'status',
        'cover_letter',
        'notes',
        'resume_url',
        'documents',
        'documents_pending',
        'internship_title',
        'student_name',
        'student_email',
        'student_course',
    ];

    protected $casts = [
        'documents' => 'array',
        'documents_pending' => 'boolean',
    ];

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

    public function ojtLogs(): HasMany
    {
        return $this->hasMany(OjtLog::class);
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }
}
