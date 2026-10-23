<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OjtDtrSubmission extends Model
{
    /** @deprecated Legacy rows only. New writes use submitted + review_stage. */
    public const STATUS_PENDING_COMPANY = 'pending_company';

    /** @deprecated Legacy rows only. New writes use submitted + review_stage. */
    public const STATUS_PENDING_COORDINATOR = 'pending_coordinator';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_REVISION_REQUIRED = 'revision_required';

    public const REVIEW_HOST = 'host';

    public const REVIEW_SCHOOL = 'school';

    protected $fillable = [
        'application_id',
        'student_id',
        'company_id',
        'submitted_by_user_id',
        'previous_submission_id',
        'period_start',
        'period_end',
        'submitted_hours',
        'status',
        'review_stage',
        'remarks',
        'rejection_reason',
        'file_path',
        'file_name',
        'file_mime',
        'file_size',
        'reviewed_by_user_id',
        'reviewed_at',
        'company_reviewed_by_user_id',
        'company_reviewed_at',
        'company_rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'submitted_hours' => 'decimal:2',
            'reviewed_at' => 'datetime',
            'company_reviewed_at' => 'datetime',
        ];
    }

    public function companyReviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'company_reviewed_by_user_id');
    }

    public static function openStatuses(): array
    {
        return [
            self::STATUS_SUBMITTED,
            self::STATUS_PENDING_COMPANY,
            self::STATUS_PENDING_COORDINATOR,
            self::STATUS_REVISION_REQUIRED,
            'pending',
        ];
    }

    public function isAwaitingHost(): bool
    {
        if ($this->status === self::STATUS_PENDING_COMPANY) {
            return true;
        }

        return $this->status === self::STATUS_SUBMITTED
            && $this->resolvedReviewStage() === self::REVIEW_HOST;
    }

    public function isAwaitingSchool(): bool
    {
        if (in_array($this->status, [self::STATUS_PENDING_COORDINATOR, 'pending'], true)) {
            return true;
        }

        return $this->status === self::STATUS_SUBMITTED
            && $this->resolvedReviewStage() === self::REVIEW_SCHOOL;
    }

    public function canonicalStatus(): string
    {
        if (in_array($this->status, [
            self::STATUS_PENDING_COMPANY,
            self::STATUS_PENDING_COORDINATOR,
            self::STATUS_SUBMITTED,
            'pending',
        ], true)) {
            return self::STATUS_SUBMITTED;
        }

        if ($this->status === self::STATUS_REVISION_REQUIRED) {
            return self::STATUS_REJECTED;
        }

        return (string) $this->status;
    }

    public function resolvedReviewStage(): ?string
    {
        $stage = strtolower(trim((string) $this->review_stage));
        if (in_array($stage, [self::REVIEW_HOST, self::REVIEW_SCHOOL], true)) {
            return $stage;
        }

        return match ($this->status) {
            self::STATUS_PENDING_COMPANY => self::REVIEW_HOST,
            self::STATUS_PENDING_COORDINATOR, 'pending' => self::REVIEW_SCHOOL,
            default => null,
        };
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function previousSubmission(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_submission_id');
    }

    public static function hoursForStudent(int $studentId, string $status, ?int $applicationId = null): float
    {
        $query = self::query()->where('student_id', $studentId);

        if ($status === 'pending') {
            $query->whereIn('status', self::openStatuses());
        } else {
            $query->where('status', $status);
        }

        if ($applicationId !== null) {
            $query->where('application_id', $applicationId);
        }

        return (float) $query->sum('submitted_hours');
    }
}
