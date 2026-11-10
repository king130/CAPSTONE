<?php

namespace App\Models;

use App\Support\CourseSkillCategoryDefaults;
use App\Support\StudentYearLevel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class SchoolCourseRequirement extends Model
{
    protected $fillable = [
        'school_id',
        'course_name',
        'course_name_key',
        'required_hours',
        'eligible_year_levels',
        'recommended_skill_categories',
    ];

    protected function casts(): array
    {
        return [
            'required_hours' => 'integer',
            'eligible_year_levels' => 'array',
            'recommended_skill_categories' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $requirement): void {
            $display = self::displayCourseName((string) $requirement->course_name);
            if ($display === '') {
                throw ValidationException::withMessages([
                    'course_name' => ['A course name is required.'],
                ]);
            }

            $requirement->course_name = $display;
            $requirement->course_name_key = self::courseNameKey($display);

            if ((int) $requirement->required_hours < 1) {
                throw ValidationException::withMessages([
                    'required_hours' => ['Required hours must be a positive integer.'],
                ]);
            }

            $levels = StudentYearLevel::normalizeList($requirement->eligible_year_levels);
            if ($levels === []) {
                $levels = StudentYearLevel::DEFAULT_ELIGIBLE;
            }
            $requirement->eligible_year_levels = $levels;

            if ($requirement->recommended_skill_categories !== null) {
                $requirement->recommended_skill_categories = CourseSkillCategoryDefaults::normalizeList(
                    $requirement->recommended_skill_categories
                );
            }
        });

        static::creating(function (self $requirement): void {
            if ($requirement->recommended_skill_categories !== null) {
                return;
            }

            $defaults = CourseSkillCategoryDefaults::forCourseName((string) $requirement->course_name);
            if ($defaults !== []) {
                $requirement->recommended_skill_categories = $defaults;
            }
        });
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public static function displayCourseName(string $courseName): string
    {
        $collapsed = preg_replace('/\s+/u', ' ', trim($courseName));

        return is_string($collapsed) ? $collapsed : '';
    }

    public static function courseNameKey(string $courseName): string
    {
        return mb_strtolower(self::displayCourseName($courseName));
    }
}
