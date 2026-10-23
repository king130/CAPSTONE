<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlacementScheduleVersion extends Model
{
    protected $fillable = [
        'application_id',
        'slots',
        'effective_from',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'slots' => 'array',
            'effective_from' => 'date',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}
