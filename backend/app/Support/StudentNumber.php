<?php

namespace App\Support;

use App\Models\Student;
use Illuminate\Validation\ValidationException;

/**
 * Shared student-number (school ID) rules for school roster and intern self-fill.
 */
class StudentNumber
{
    public const MIN_LENGTH = 3;

    public const MAX_LENGTH = 32;

    /** Letters, digits, hyphen, slash, period, underscore — no spaces after trim. */
    public const PATTERN = '/^[A-Za-z0-9][A-Za-z0-9\\/\\._-]{2,31}$/';

    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        // Collapse internal whitespace that users may paste from spreadsheets.
        $collapsed = preg_replace('/\s+/', '', $trimmed) ?? $trimmed;

        return $collapsed === '' ? null : $collapsed;
    }

    /**
     * @return array{ok: true, value: string}|array{ok: false, message: string}
     */
    public static function validate(?string $raw, bool $required = true): array
    {
        $value = self::normalize($raw);

        if ($value === null) {
            if ($required) {
                return [
                    'ok' => false,
                    'message' => 'Student number is required.',
                ];
            }

            return ['ok' => true, 'value' => ''];
        }

        if (strlen($value) < self::MIN_LENGTH || strlen($value) > self::MAX_LENGTH) {
            return [
                'ok' => false,
                'message' => 'Student number must be between 3 and 32 characters (letters, numbers, - / . _).',
            ];
        }

        if (! preg_match(self::PATTERN, $value)) {
            return [
                'ok' => false,
                'message' => 'Student number may only use letters, numbers, hyphens, slashes, periods, and underscores (no spaces).',
            ];
        }

        return ['ok' => true, 'value' => $value];
    }

    /**
     * Ensure uniqueness within a school. Does not reveal the other student's identity.
     *
     * @throws ValidationException
     */
    public static function assertUniqueInSchool(int $schoolId, string $studentNumber, ?int $exceptStudentId = null): void
    {
        $query = Student::query()
            ->where('school_id', $schoolId)
            ->whereRaw('LOWER(TRIM(student_id_number)) = ?', [strtolower($studentNumber)]);

        if ($exceptStudentId !== null) {
            $query->where('id', '!=', $exceptStudentId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'studentNumber' => [
                    'That student number is already assigned to another student in this school. Use a different number.',
                ],
            ]);
        }
    }
}
