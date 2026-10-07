<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationAssessment extends Model
{
    protected $fillable = [
        'application_id',
        'assessed_by_user_id',
        'stage',
        'rubric_scores',
        'overall_score',
        'comments',
    ];

    protected function casts(): array
    {
        return [
            'rubric_scores' => 'array',
            'overall_score' => 'decimal:2',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function assessedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessed_by_user_id');
    }
}
