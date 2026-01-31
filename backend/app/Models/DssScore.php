<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DssScore extends Model
{
    protected $fillable = [
        'student_id',
        'internship_id',
        'score',
        'details',
    ];

    protected $casts = [
        'details' => 'array',
    ];
}
