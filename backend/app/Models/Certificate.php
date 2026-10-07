<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Certificate extends Model
{
    protected $fillable = [
        'student_id',
        'application_id',
        'organization_id',
        'certificate_number',
        'issued_by_user_id',
        'issued_at',
        'file_path',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by_user_id');
    }

    public static function generateCertificateNumber(int $studentId): string
    {
        $year = now()->year;

        do {
            $candidate = sprintf('CERT-%d-%04d-%s', $year, $studentId, strtoupper(\Illuminate\Support\Str::random(5)));
        } while (self::query()->where('certificate_number', $candidate)->exists());

        return $candidate;
    }
}
