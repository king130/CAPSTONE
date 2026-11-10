<?php

namespace App\Http\Controllers;

use App\Models\OrganizationMembership;
use App\Models\School;
use App\Models\SchoolCourseRequirement;
use App\Models\User;
use App\Services\CourseYearLevelGate;
use App\Services\PermissionGate;
use App\Support\CourseSkillCategoryDefaults;
use App\Support\StudentYearLevel;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class SchoolCourseRequirementController extends Controller
{
    public function __construct(
        private readonly PermissionGate $permissions,
        private readonly CourseYearLevelGate $courseYearLevels,
    ) {
    }

    public function index(Request $request)
    {
        $school = $this->resolveSchool($request->user());

        $requirements = SchoolCourseRequirement::query()
            ->where('school_id', $school->id)
            ->orderBy('course_name')
            ->get()
            ->map(fn (SchoolCourseRequirement $requirement) => $this->serialize($requirement))
            ->values();

        $incomplete = $this->courseYearLevels->incompleteCourses($school);
        $flagged = $this->courseYearLevels->flaggedStudents($school);

        return response()->json([
            'data' => $requirements,
            'meta' => [
                'allowedYearLevels' => StudentYearLevel::OPTIONS,
                'defaultEligibleYearLevels' => StudentYearLevel::DEFAULT_ELIGIBLE,
                'skillCatalogCategories' => CourseSkillCategoryDefaults::catalogCategories(),
                'incompleteCourses' => $incomplete,
                'flaggedStudentCount' => $flagged->count(),
                'hasSetupWarnings' => $incomplete !== [] || $flagged->isNotEmpty() || $requirements->isEmpty(),
            ],
        ]);
    }

    public function flaggedStudents(Request $request)
    {
        $school = $this->resolveSchool($request->user());

        return response()->json([
            'data' => $this->courseYearLevels->flaggedStudents($school)->values(),
        ]);
    }

    public function store(Request $request)
    {
        $school = $this->resolveSchool($request->user());
        $data = $this->validated($request);

        $key = SchoolCourseRequirement::courseNameKey($data['courseName']);
        if (SchoolCourseRequirement::query()->where('school_id', $school->id)->where('course_name_key', $key)->exists()) {
            return $this->duplicateResponse();
        }

        try {
            $createPayload = [
                'school_id' => $school->id,
                'course_name' => $data['courseName'],
                'course_name_key' => $key,
                'required_hours' => $data['requiredHours'],
                'eligible_year_levels' => $data['eligibleYearLevels'],
            ];
            if (array_key_exists('recommendedSkillCategories', $data)) {
                $createPayload['recommended_skill_categories'] = $data['recommendedSkillCategories'];
            }

            $requirement = SchoolCourseRequirement::query()->create($createPayload);
        } catch (QueryException $exception) {
            if ($this->isUniqueViolation($exception)) {
                return $this->duplicateResponse();
            }

            throw $exception;
        }

        return response()->json(['data' => $this->serialize($requirement)], Response::HTTP_CREATED);
    }

    public function update(Request $request, SchoolCourseRequirement $schoolCourseRequirement)
    {
        $school = $this->resolveSchool($request->user());
        if ((int) $schoolCourseRequirement->school_id !== (int) $school->id) {
            return response()->json(['message' => 'Not found.'], Response::HTTP_NOT_FOUND);
        }

        $data = $this->validated($request);
        $key = SchoolCourseRequirement::courseNameKey($data['courseName']);
        $duplicate = SchoolCourseRequirement::query()
            ->where('school_id', $school->id)
            ->where('course_name_key', $key)
            ->where('id', '!=', $schoolCourseRequirement->id)
            ->exists();

        if ($duplicate) {
            return $this->duplicateResponse();
        }

        try {
            $updatePayload = [
                'course_name' => $data['courseName'],
                'course_name_key' => $key,
                'required_hours' => $data['requiredHours'],
                'eligible_year_levels' => $data['eligibleYearLevels'],
            ];
            if (array_key_exists('recommendedSkillCategories', $data)) {
                $updatePayload['recommended_skill_categories'] = $data['recommendedSkillCategories'];
            }

            $schoolCourseRequirement->fill($updatePayload)->save();
        } catch (QueryException $exception) {
            if ($this->isUniqueViolation($exception)) {
                return $this->duplicateResponse();
            }

            throw $exception;
        }

        return response()->json(['data' => $this->serialize($schoolCourseRequirement->fresh())]);
    }

    /**
     * @return array{
     *     courseName: string,
     *     requiredHours: int,
     *     eligibleYearLevels: list<string>,
     *     recommendedSkillCategories?: list<string>
     * }
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'courseName' => ['required', 'string', 'max:255'],
            'requiredHours' => ['required', 'integer', 'min:1', 'max:10000'],
            'eligibleYearLevels' => ['sometimes', 'array'],
            'eligibleYearLevels.*' => ['string', 'max:32'],
            'recommendedSkillCategories' => ['sometimes', 'array'],
            'recommendedSkillCategories.*' => ['string', 'max:64'],
        ]);

        $courseName = SchoolCourseRequirement::displayCourseName($data['courseName']);
        if ($courseName === '') {
            throw ValidationException::withMessages([
                'courseName' => ['A course name is required.'],
            ]);
        }

        $levels = array_key_exists('eligibleYearLevels', $data)
            ? StudentYearLevel::normalizeList($data['eligibleYearLevels'])
            : StudentYearLevel::DEFAULT_ELIGIBLE;

        if ($levels === []) {
            throw ValidationException::withMessages([
                'eligibleYearLevels' => ['Select at least one eligible year level (1st Year through 5th Year).'],
            ]);
        }

        $invalid = [];
        foreach (($data['eligibleYearLevels'] ?? []) as $raw) {
            if (! is_string($raw) && ! is_numeric($raw)) {
                $invalid[] = (string) $raw;
                continue;
            }
            if (StudentYearLevel::normalize((string) $raw) === null) {
                $invalid[] = (string) $raw;
            }
        }
        if ($invalid !== []) {
            throw ValidationException::withMessages([
                'eligibleYearLevels' => ['Invalid year level values: '.implode(', ', $invalid).'. Allowed: '.implode(', ', StudentYearLevel::OPTIONS).'.'],
            ]);
        }

        $payload = [
            'courseName' => $courseName,
            'requiredHours' => (int) $data['requiredHours'],
            'eligibleYearLevels' => $levels,
        ];

        if (array_key_exists('recommendedSkillCategories', $data)) {
            $payload['recommendedSkillCategories'] = CourseSkillCategoryDefaults::normalizeList($data['recommendedSkillCategories']);
        }

        return $payload;
    }

    private function resolveSchool(?User $user): School
    {
        if (! $user) {
            abort(Response::HTTP_UNAUTHORIZED);
        }

        $membership = $user->primaryOrganizationMembership();
        $organization = $membership?->organization;

        if (! $membership || ! $organization || $organization->type !== 'school') {
            abort(Response::HTTP_FORBIDDEN, 'Only schools can manage course OJT requirements.');
        }

        $membership->loadMissing(['role.permissions', 'organization']);

        if (! $this->canManageCourseRequirements($user, $membership)) {
            abort(Response::HTTP_FORBIDDEN, 'Only authorized school administrators can manage course OJT requirements.');
        }

        $school = School::query()->where('organization_id', $organization->id)->first();
        if (! $school) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'This school organization is missing a School record.');
        }

        return $school;
    }

    private function canManageCourseRequirements(User $user, OrganizationMembership $membership): bool
    {
        if ($this->permissions->userCan($user, 'org.manage_students')) {
            return true;
        }

        $permissions = $membership->effectivePermissions();

        return in_array('org.manage_students', $permissions, true)
            || in_array('manage_users', $permissions, true)
            || in_array('org.manage_members', $permissions, true);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(SchoolCourseRequirement $requirement): array
    {
        $levels = StudentYearLevel::normalizeList($requirement->eligible_year_levels);
        if ($levels === []) {
            $levels = StudentYearLevel::DEFAULT_ELIGIBLE;
        }

        $recommended = CourseSkillCategoryDefaults::normalizeList($requirement->recommended_skill_categories);
        if ($recommended === [] && filled($requirement->course_name)) {
            $recommended = CourseSkillCategoryDefaults::forCourseName((string) $requirement->course_name);
        }

        return [
            'id' => (string) $requirement->id,
            'schoolId' => (string) $requirement->school_id,
            'courseName' => $requirement->course_name,
            'requiredHours' => (int) $requirement->required_hours,
            'eligibleYearLevels' => $levels,
            'recommendedSkillCategories' => $recommended,
            'configComplete' => (int) $requirement->required_hours >= 1 && $levels !== [],
        ];
    }

    private function duplicateResponse()
    {
        return response()->json([
            'message' => 'This school already has a required-hours setting for that course.',
            'code' => 'duplicate_course_requirement',
            'errors' => [
                'courseName' => ['This school already has a required-hours setting for that course.'],
            ],
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        $sqlState = (string) $exception->getCode();

        return in_array($sqlState, ['23000', '23505'], true);
    }
}
