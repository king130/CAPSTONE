<?php

namespace App\Models;

use App\Support\SkillNameNormalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Skill extends Model
{
    public const STATUS_APPROVED = 'approved';

    public const STATUS_PENDING = 'pending_review';

    public const STATUS_REJECTED = 'rejected';

    public const MAX_STUDENT = 15;

    public const MAX_JOB = 10;

    protected $fillable = [
        'name',
        'normalized_name',
        'category',
        'status',
        'created_by_user_id',
        'merged_into_skill_id',
    ];

    protected static function booted(): void
    {
        static::saving(function (Skill $skill): void {
            $skill->name = SkillNameNormalizer::displayName((string) $skill->name);
            $skill->normalized_name = SkillNameNormalizer::normalize((string) $skill->name);
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(self::class, 'merged_into_skill_id');
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'student_skill')
            ->withPivot(['level'])
            ->withTimestamps();
    }

    public function internships(): BelongsToMany
    {
        return $this->belongsToMany(Internship::class, 'internship_skill')
            ->withPivot(['importance'])
            ->withTimestamps();
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        return $query->where(function (Builder $builder) use ($user) {
            $builder->where('status', self::STATUS_APPROVED);
            if ($user) {
                $builder->orWhere(function (Builder $owned) use ($user) {
                    $owned->where('status', self::STATUS_PENDING)
                        ->where('created_by_user_id', $user->id);
                });
            }
        });
    }

    public function toApiArray(): array
    {
        return [
            'id' => (string) $this->id,
            'name' => $this->name,
            'category' => $this->category,
            'status' => $this->status,
            'createdByUserId' => $this->created_by_user_id ? (string) $this->created_by_user_id : null,
            'mergedIntoSkillId' => $this->merged_into_skill_id ? (string) $this->merged_into_skill_id : null,
        ];
    }
}
