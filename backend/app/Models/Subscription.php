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
    ];

    public function organizations(): HasMany
    {
        return $this->hasMany(Organization::class);
    }
}
