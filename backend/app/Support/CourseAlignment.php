<?php

namespace App\Support;

/**
 * Course list normalization + alignment for partner directory matching.
 * Mirrors the New Agreement form rule: trim, collapse whitespace, case-insensitive match.
 */
class CourseAlignment
{
    public const ALIGNED = 'aligned';

    public const NONE = 'none';

    public const UNSPECIFIED = 'unspecified';

    /**
     * @return list<string> Unique display names (first-seen casing), keyed by normalized match key.
     */
    public static function normalizeList(mixed $courses): array
    {
        if (! is_array($courses)) {
            return [];
        }

        $unique = [];
        foreach ($courses as $course) {
            if (! is_string($course)) {
                continue;
            }
            $display = self::displayName($course);
            if ($display === '') {
                continue;
            }
            $key = self::courseKey($display);
            if ($key === '' || isset($unique[$key])) {
                continue;
            }
            $unique[$key] = $display;
        }

        return array_values($unique);
    }

    public static function displayName(string $course): string
    {
        $collapsed = preg_replace('/\s+/u', ' ', trim($course));

        return is_string($collapsed) ? $collapsed : '';
    }

    public static function courseKey(string $course): string
    {
        return mb_strtolower(self::displayName($course));
    }

    /**
     * Shared courses using the viewer's display names for matches.
     *
     * @param  list<string>  $viewerCourses
     * @param  list<string>  $otherCourses
     * @return list<string>
     */
    public static function sharedCourses(array $viewerCourses, array $otherCourses): array
    {
        $viewer = self::normalizeList($viewerCourses);
        $other = self::normalizeList($otherCourses);
        if ($viewer === [] || $other === []) {
            return [];
        }

        $otherKeys = [];
        foreach ($other as $course) {
            $otherKeys[self::courseKey($course)] = true;
        }

        $shared = [];
        foreach ($viewer as $course) {
            if (isset($otherKeys[self::courseKey($course)])) {
                $shared[] = $course;
            }
        }

        return $shared;
    }

    /**
     * @param  list<string>  $viewerCourses
     * @param  list<string>  $otherCourses
     */
    public static function alignment(array $viewerCourses, array $otherCourses): string
    {
        $other = self::normalizeList($otherCourses);
        if ($other === []) {
            return self::UNSPECIFIED;
        }

        return self::sharedCourses($viewerCourses, $other) !== []
            ? self::ALIGNED
            : self::NONE;
    }

    public static function includeWhenAlignedFilter(string $alignment, bool $isPartnered): bool
    {
        if ($isPartnered) {
            return true;
        }

        return $alignment === self::ALIGNED || $alignment === self::UNSPECIFIED;
    }
}
