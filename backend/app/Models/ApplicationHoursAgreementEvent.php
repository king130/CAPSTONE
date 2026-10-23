<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationHoursAgreementEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'agreement_id',
        'application_id',
        'action',
        'from_status',
        'to_status',
        'proposed_hours',
        'agreed_hours',
        'changed_by_user_id',
        'changed_by_side',
        'changed_by_role',
        'note',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'proposed_hours' => 'integer',
            'agreed_hours' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function agreement(): BelongsTo
    {
        return $this->belongsTo(ApplicationHoursAgreement::class, 'agreement_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}
