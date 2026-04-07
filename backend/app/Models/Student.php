<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Student extends Model
{
    protected $fillable = [
        'user_id',
        'school_id',
        'organization_id',
        'intern_code',
        'student_id_number',
        'school_name',
        'school_subscription_code',
        'course',
        'year_level',
        'preferred_field',
        'contact_number',
    ];

    protected static function booted(): void
    {
        static::creating(function (Student $student): void {
            if (filled($student->intern_code)) {
                return;
            }

            $student->intern_code = self::generateInternCode(
                $student->school_id ? School::query()->find($student->school_id) : null,
                $student->school_subscription_code
            );
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public static function generateInternCode(?School $school = null, ?string $schoolSubscriptionCode = null): string
    {
        $prefixSource = $school?->subscription_code ?: $schoolSubscriptionCode ?: $school?->institution_name ?: 'SCH';
        $prefix = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', (string) $prefixSource) ?: 'SCH', 0, 6));
        $prefix = str_pad($prefix, 3, 'X');

        do {
            $candidate = sprintf('%s-INT-%s', $prefix, strtoupper(Str::random(6)));
        } while (self::query()->where('intern_code', $candidate)->exists());

        return $candidate;
    }
}
