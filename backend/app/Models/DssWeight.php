<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DssWeight extends Model
{
    protected $fillable = [
        'name',
        'weight',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
