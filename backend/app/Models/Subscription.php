<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    protected $fillable = [
        'plan',
        'status',
        'billing_cycle',
        'starts_at',
        'ends_at',
    ];
}
