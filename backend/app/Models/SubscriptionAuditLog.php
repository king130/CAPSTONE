<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionAuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'organization_id',
        'subscription_id',
        'actor_user_id',
        'event',
        'from_plan',
        'to_plan',
        'from_status',
        'to_status',
        'reason',
        'meta',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
