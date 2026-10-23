<?php

namespace App\Services;

use App\Models\School;
use App\Models\SchoolCourseRequirement;
use App\Models\Student;
use App\Support\StudentYearLevel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

/**
 * Course-scoped year-level eligibility and missing-config checks.
 * Required hours for applications/calendar still flow through RequiredOjtHoursAccessor snapshots.
 */
class CourseYearLevelGate
{
    public function requirementFor(Student $student): ?SchoolCourseRequirement
    {
        $course = trim((string) ($student->course ?? ''));
        if (! $student->school_id || $course === '') {
            return null;
        }

        return SchoolCourseRequirement::query()
            ->where('school_id', $student->school_id)
            ->where('course_name_key', SchoolCourseRequirement::courseNameKey($course))
            ->first();
    }

    /**
     * @return list<string>
     */
    public function eligibleYearsForRequirement(?SchoolCourseRequirement $requirement): array
    {
        if (! $requirement) {
            return [];
        }

        return StudentYearLevel::normalizeList($requirement->eligible_year_levels);
    }

    public function isConfigComplete(?SchoolCourseRequirement $requirement): bool
    {
        if (! $requirement) {
            return false;
        }

        if ((int) $requirement->required_hours < 1) {
            return false;
        }

        return $this->eligibleYearsForRequirement($requirement) !== [];
    }

    /**
     * Reject applying when the course is incomplete or the student's year is not eligible.
     * Does not modify the student record.
     */
    public function rejectIfCannotApply(Student $student): ?JsonResponse
    {
        $requirement = $this->requirementFor($student);

        if (! $this->isConfigComplete($requirement)) {
            return response()->json([
                'message' => 'Your school has not finished setup for your course. Required OJT hours and eligible year levels must be configured before you can apply.',
                'code' => 'course_setup_incomplete',
                'errors' => [
                    'course' => ['Your school has not finished setup for your course.'],
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $year = StudentYearLevel::normalize($student->year_level);
        $eligible = $this->eligibleYearsForRequirement($requirement);
        if ($year === null || ! StudentYearLevel::isEligible($year, $eligible)) {
            return response()->json([
                'message' => 'Your year level is not eligible for OJT in this course. Ask your school administrator to update your record or the course settings.',
                'code' => 'year_level_not_eligible',
                'errors' => [
                    'yearLevel' => ['Your year level is not eligible for this course.'],
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return null;
    }

    /**
     * Validate a proposed year level against the student's (or proposed) course.
     *
     * @return array{ok: bool, message?: string, normalized?: string}
     */
    public function validateYearForCourse(int $schoolId, ?string $courseName, ?string $yearLevel): array
    {
        $normalized = StudentYearLevel::normalize($yearLevel);
        if ($normalized === null) {
            return [
                'ok' => false,
                'message' => 'Year level must be 1st Year through 5th Year.',
            ];
        }

        $course = trim((string) $courseName);
        if ($course === '') {
            return ['ok' => true, 'normalized' => $normalized];
        }

        $requirement = SchoolCourseRequirement::query()
            ->where('school_id', $schoolId)
            ->where('course_name_key', SchoolCourseRequirement::courseNameKey($course))
            ->first();

        if (! $requirement) {
            // Course not configured yet — accept canonical year; apply will still block until setup is finished.
            return ['ok' => true, 'normalized' => $normalized];
        }

        $eligible = $this->eligibleYearsForRequirement($requirement);
        if ($eligible === []) {
            return [
                'ok' => false,
                'message' => 'This course has no eligible year levels configured. Ask a school administrator to finish course setup.',
            ];
        }

        if (! StudentYearLevel::isEligible($normalized, $eligible)) {
            return [
                'ok' => false,
                'message' => 'Year level must be one of: '.implode(', ', $eligible).'.',
            ];
        }

        return ['ok' => true, 'normalized' => $normalized];
    }

    /**
     * Students whose year is set but not in their course's eligible list (or course missing/incomplete).
     * Does not modify records.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function flaggedStudents(School $school): Collection
    {
        $requirements = SchoolCourseRequirement::query()
            ->where('school_id', $school->id)
            ->get()
            ->keyBy('course_name_key');

        return Student::query()
            ->where('school_id', $school->id)
            ->with('user:id,name,email')
            ->orderBy('id')
            ->get()
            ->map(function (Student $student) use ($requirements) {
                $course = trim((string) ($student->course ?? ''));
                $year = StudentYearLevel::normalize($student->year_level);
                $key = $course !== '' ? SchoolCourseRequirement::courseNameKey($course) : '';
                $requirement = $key !== '' ? ($requirements->get($key)) : null;
                $eligible = $this->eligibleYearsForRequirement($requirement);

                $reasons = [];
                if ($course === '') {
                    $reasons[] = 'missing_course';
                } elseif (! $requirement) {
                    $reasons[] = 'course_not_configured';
                } elseif ($eligible === []) {
                    $reasons[] = 'no_eligible_year_levels';
                } elseif ($year === null) {
                    $reasons[] = 'missing_or_invalid_year_level';
                } elseif (! StudentYearLevel::isEligible($year, $eligible)) {
                    $reasons[] = 'year_not_eligible_for_course';
                }

                if ($reasons === []) {
                    return null;
                }

                return [
                    'id' => (string) $student->id,
                    'userId' => (string) $student->user_id,
                    'name' => $student->user?->name,
                    'email' => $student->user?->email,
                    'course' => $student->course,
                    'yearLevel' => $student->year_level,
                    'eligibleYearLevels' => $eligible,
                    'reasons' => $reasons,
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function incompleteCourses(School $school): array
    {
        return SchoolCourseRequirement::query()
            ->where('school_id', $school->id)
            ->orderBy('course_name')
            ->get()
            ->filter(fn (SchoolCourseRequirement $requirement) => ! $this->isConfigComplete($requirement))
            ->map(fn (SchoolCourseRequirement $requirement) => [
                'id' => (string) $requirement->id,
                'courseName' => $requirement->course_name,
                'requiredHours' => (int) $requirement->required_hours,
                'eligibleYearLevels' => $this->eligibleYearsForRequirement($requirement),
                'missingHours' => (int) $requirement->required_hours < 1,
                'missingYearLevels' => $this->eligibleYearsForRequirement($requirement) === [],
            ])
            ->values()
            ->all();
    }
}
