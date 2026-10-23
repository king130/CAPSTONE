<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlacementSchedule extends Model
{
    protected $fillable = [
        'application_id',
        'active_slots',
        'status',
        'source',
        'ojt_start_date',
        'ojt_start_locked_at',
    ];

    protected function casts(): array
    {
        return [
            'active_slots' => 'array',
            'ojt_start_date' => 'date',
            'ojt_start_locked_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}
