<?php

namespace App\Support;

use App\Models\SchoolCourseRequirement;
use App\Models\Student;

class CourseSkillCategoryDefaults
{
    /**
     * Skill catalog categories (must match SkillCatalogSeeder).
     *
     * @return list<string>
     */
    public static function catalogCategories(): array
    {
        return [
            'IT & Software',
            'Business',
            'Engineering',
            'Education',
            'Hospitality',
            'Healthcare',
            'Communication',
            'Creative & Design',
            'Agriculture',
        ];
    }

    /**
     * Suggested skill categories for a course display name.
     *
     * @return list<string>
     */
    /**
     * @return list<string>
     */
    public static function forStudent(?Student $student): array
    {
        if (! $student) {
            return [];
        }

        $course = SchoolCourseRequirement::displayCourseName((string) ($student->course ?? ''));
        if ($course === '') {
            return [];
        }

        if ($student->school_id) {
            $requirement = SchoolCourseRequirement::query()
                ->where('school_id', $student->school_id)
                ->where('course_name_key', SchoolCourseRequirement::courseNameKey($course))
                ->first();

            if ($requirement) {
                $stored = $requirement->recommended_skill_categories;
                if ($stored !== null) {
                    return self::normalizeList($stored);
                }
            }
        }

        return self::forCourseName($course);
    }

    /**
     * Union of recommended categories for multiple course names (job eligible courses).
     *
     * @param  list<string>  $courseNames
     * @return list<string>
     */
    public static function forCourseNames(array $courseNames): array
    {
        $merged = [];
        $seen = [];

        foreach ($courseNames as $raw) {
            if (! is_string($raw)) {
                continue;
            }
            foreach (self::forCourseName($raw) as $category) {
                if (isset($seen[$category])) {
                    continue;
                }
                $seen[$category] = true;
                $merged[] = $category;
            }
        }

        return $merged;
    }

    /**
     * @return list<string>
     */
    public static function forCourseName(string $courseName): array
    {
        $display = SchoolCourseRequirement::displayCourseName($courseName);
        if ($display === '') {
            return [];
        }

        $lower = mb_strtolower($display);

        $primary = self::matchPrimaryCategory($lower);
        if ($primary === null) {
            return [];
        }

        return self::normalizeList([$primary, 'Communication']);
    }

    /**
     * Accepts array, JSON string, or null (production rows may still be raw JSON text).
     *
     * @param  list<string>|string|null  $categories
     * @return list<string>
     */
    public static function normalizeList(array|string|null $categories): array
    {
        if (is_string($categories)) {
            $decoded = json_decode($categories, true);
            $categories = is_array($decoded) ? $decoded : [];
        }

        if ($categories === null || $categories === []) {
            return [];
        }

        $allowed = array_fill_keys(self::catalogCategories(), true);
        $seen = [];
        $out = [];

        foreach ($categories as $raw) {
            if (! is_string($raw)) {
                continue;
            }
            $name = trim($raw);
            if ($name === '' || ! isset($allowed[$name]) || isset($seen[$name])) {
                continue;
            }
            $seen[$name] = true;
            $out[] = $name;
        }

        return $out;
    }

    private static function matchPrimaryCategory(string $lower): ?string
    {
        if (self::matchesAny($lower, [
            'information technology',
            'computer science',
            'software engineering',
            'software development',
            'data science',
            'cyber security',
            'cybersecurity',
            'information systems',
            'computer engineering',
            'bsit',
            'bscs',
            'bs cs',
            'bs it',
            ' bsit',
            ' bscs',
        ]) || preg_match('/\b(it|ict)\b/u', $lower) === 1) {
            return 'IT & Software';
        }

        if (self::matchesAny($lower, [
            'business administration',
            'business management',
            'accountancy',
            'accounting',
            'marketing',
            'entrepreneurship',
            'finance',
            'economics',
            'management',
            'bsba',
            'bsa',
            'bba',
        ]) || str_contains($lower, 'business')) {
            return 'Business';
        }

        if (self::matchesAny($lower, [
            'engineering',
            'civil engineering',
            'mechanical engineering',
            'electrical engineering',
            'electronics engineering',
            'industrial engineering',
            'computer engineering',
            'bsce',
            'bsme',
            'bsee',
        ])) {
            return 'Engineering';
        }

        if (self::matchesAny($lower, [
            'education',
            'teaching',
            'elementary education',
            'secondary education',
            'beed',
            'bsed',
        ])) {
            return 'Education';
        }

        if (self::matchesAny($lower, [
            'hospitality',
            'hotel',
            'restaurant',
            'tourism',
            'culinary',
            'hrm',
            'human resource',
        ])) {
            return 'Hospitality';
        }

        if (self::matchesAny($lower, [
            'nursing',
            'medical',
            'health',
            'pharmacy',
            'midwifery',
            'radiologic',
            'physical therapy',
            'bsn',
        ])) {
            return 'Healthcare';
        }

        if (self::matchesAny($lower, [
            'agriculture',
            'agribusiness',
            'forestry',
            'animal science',
            'crop science',
        ])) {
            return 'Agriculture';
        }

        if (self::matchesAny($lower, [
            'multimedia',
            'graphic design',
            'fine arts',
            'visual communication',
            'animation',
            'film',
            'creative',
            'design',
        ])) {
            return 'Creative & Design';
        }

        return null;
    }

    /**
     * @param  list<string>  $needles
     */
    private static function matchesAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}
