<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OjtAttendanceEvent extends Model
{
    public const TYPE_ABSENT_EXCUSED = 'absent_excused';

    public const TYPE_ABSENT_UNEXCUSED = 'absent_unexcused';

    protected $fillable = [
        'application_id',
        'student_id',
        'date',
        'type',
        'reason',
        'marked_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public static function absenceTypes(): array
    {
        return [self::TYPE_ABSENT_EXCUSED, self::TYPE_ABSENT_UNEXCUSED];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function markedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by_user_id');
    }
}
