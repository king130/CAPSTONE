<?php

namespace App\Support;

final class StudentYearLevel
{
    /** Canonical labels stored in the database. */
    public const OPTIONS = [
        '1st Year',
        '2nd Year',
        '3rd Year',
        '4th Year',
        '5th Year',
    ];

    /** Default eligible years for a newly configured course. */
    public const DEFAULT_ELIGIBLE = [
        '3rd Year',
        '4th Year',
    ];

    public static function isValid(?string $value): bool
    {
        if ($value === null) {
            return false;
        }

        $normalized = self::normalize($value);

        return $normalized !== null && in_array($normalized, self::OPTIONS, true);
    }

    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $raw = trim($value);
        if ($raw === '') {
            return null;
        }

        if (in_array($raw, self::OPTIONS, true)) {
            return $raw;
        }

        $compact = strtolower(preg_replace('/\s+/', ' ', $raw) ?? $raw);
        $compact = str_replace(['year', 'yr', '.'], '', $compact);
        $compact = trim($compact);

        return match ($compact) {
            '1', '1st', 'first', 'i' => '1st Year',
            '2', '2nd', 'second', 'ii' => '2nd Year',
            '3', '3rd', 'third', 'iii' => '3rd Year',
            '4', '4th', 'fourth', 'iv' => '4th Year',
            '5', '5th', 'fifth', 'v' => '5th Year',
            default => null,
        };
    }

    /**
     * Normalize a list of year-level labels to canonical OPTIONS.
     * Drops unknowns; de-duplicates; preserves OPTIONS order.
     *
     * @param  mixed  $input
     * @return list<string>
     */
    public static function normalizeList(mixed $input): array
    {
        if ($input === null || $input === '') {
            return [];
        }

        if (is_string($input)) {
            $decoded = json_decode($input, true);
            $input = is_array($decoded) ? $decoded : (preg_split('/\s*,\s*/', $input) ?: []);
        }

        if (! is_array($input)) {
            return [];
        }

        $found = [];
        foreach ($input as $item) {
            $normalized = self::normalize(is_string($item) || is_numeric($item) ? (string) $item : null);
            if ($normalized !== null) {
                $found[$normalized] = true;
            }
        }

        $ordered = [];
        foreach (self::OPTIONS as $option) {
            if (isset($found[$option])) {
                $ordered[] = $option;
            }
        }

        return $ordered;
    }

    /**
     * @param  list<string>  $eligible
     */
    public static function isEligible(?string $yearLevel, array $eligible): bool
    {
        $normalized = self::normalize($yearLevel);
        if ($normalized === null) {
            return false;
        }

        $allowed = self::normalizeList($eligible);

        return in_array($normalized, $allowed, true);
    }

    /**
     * Accept any canonical year (1st–5th) or safe aliases.
     * Course eligibility is enforced separately.
     *
     * @return array<int, mixed>
     */
    public static function validationRule(): array
    {
        return ['required', 'string', self::normalizableRule()];
    }

    /**
     * @return array<int, mixed>
     */
    public static function optionalValidationRule(): array
    {
        return ['nullable', 'string', self::normalizableRule()];
    }

    private static function normalizableRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if ($value === null || $value === '') {
                return;
            }
            if (! is_string($value) && ! is_numeric($value)) {
                $fail('Year level must be 1st Year through 5th Year.');

                return;
            }
            if (self::normalize((string) $value) === null) {
                $fail('Year level must be 1st Year through 5th Year.');
            }
        };
    }
}
