<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    protected $fillable = [
        'plan',
        'status',
        'billing_cycle',
        'starts_at',
        'ends_at',
        'trial_started_at',
        'trial_ends_at',
        'grace_ends_at',
        'access_note',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'ends_at' => 'date',
            'trial_started_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'grace_ends_at' => 'datetime',
        ];
    }

    public function organizations(): HasMany
    {
        return $this->hasMany(Organization::class);
    }
}
