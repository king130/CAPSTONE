<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApplicationHoursAgreement extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_AGREED = 'agreed';

    public const STATUS_DECLINED = 'declined';

    public const SIDE_SCHOOL = 'school';

    public const SIDE_HOST = 'host';

    protected $fillable = [
        'application_id',
        'school_required_hours',
        'host_offered_hours',
        'proposed_hours',
        'agreed_hours',
        'status',
        'proposed_by_side',
        'proposed_by_user_id',
        'school_confirmed_at',
        'school_confirmed_by_user_id',
        'host_confirmed_at',
        'host_confirmed_by_user_id',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'school_required_hours' => 'integer',
            'host_offered_hours' => 'integer',
            'proposed_hours' => 'integer',
            'agreed_hours' => 'integer',
            'school_confirmed_at' => 'datetime',
            'host_confirmed_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ApplicationHoursAgreementEvent::class, 'agreement_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isAgreed(): bool
    {
        return $this->status === self::STATUS_AGREED;
    }
}
