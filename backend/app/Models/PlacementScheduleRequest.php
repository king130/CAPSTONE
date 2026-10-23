<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlacementScheduleRequest extends Model
{
    protected $fillable = [
        'application_id',
        'requested_by_user_id',
        'requested_slots',
        'reason',
        'status',
        'decided_by_user_id',
        'decided_by_role',
        'decided_at',
        'decision_note',
    ];

    protected function casts(): array
    {
        return [
            'requested_slots' => 'array',
            'decided_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }
}
