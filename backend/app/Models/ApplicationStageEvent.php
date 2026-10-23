<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationStageEvent extends Model
{
    protected $fillable = [
        'application_id',
        'from_stage',
        'to_stage',
        'changed_by_user_id',
        'changed_by_role',
        'organization_id',
        'organization_name',
        'reason',
        'is_rollback',
    ];

    protected function casts(): array
    {
        return [
            'is_rollback' => 'boolean',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
