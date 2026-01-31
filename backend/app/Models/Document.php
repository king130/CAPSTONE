<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    protected $fillable = [
        'owner_id',
        'owner_type',
        'category',
        'file_name',
        'file_url',
        'file_type',
        'file_size',
        'storage_provider',
        'verified_at',
    ];

    protected $casts = [
        'verified_at' => 'datetime',
    ];
}
