<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Role;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SchoolStudentController extends Controller
{
    public function index(Request $request)
    {
        $school = $this->resolveSchool($request->user());

        $students = Student::query()
            ->with('user')
            ->where(function ($query) use ($school) {
                $query->where('school_id', $school->id);
                if ($school->subscription_code) {
                    $query->orWhere('school_subscription_code', $school->subscription_code);
                }
            })
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Student $student) => $this->transformStudent($student))
            ->values()
            ->all();

        return response()->json(['data' => $students]);
    }

    public function store(Request $request)
    {
        $school = $this->resolveSchool($request->user());

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'studentName' => ['nullable', 'string', 'max:255'],
            'studentNumber' => ['nullable', 'string', 'max:255'],
            'course' => ['nullable', 'string', 'max:255'],
            'yearLevel' => ['nullable', 'string', 'max:255'],
        ]);

        $email = strtolower(trim($data['email']));
        if (User::query()->where('email', $email)->exists()) {
            return response()->json(['message' => 'That student email is already in use.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $defaultPassword = $this->generatePassword();
        $student = DB::transaction(function () use ($school, $data, $email, $defaultPassword) {
            $user = User::query()->create([
                'name' => trim((string) ($data['studentName'] ?? Str::before($email, '@'))),
                'email' => $email,
                'password' => $defaultPassword,
                'role' => 'student',
                'profile' => [
                    'studentNumber' => $data['studentNumber'] ?? null,
                    'studentId' => $data['studentNumber'] ?? null,
                    'schoolName' => $school->institution_name,
                    'schoolId' => (string) $school->id,
                    'course' => $data['course'] ?? null,
                    'yearLevel' => $data['yearLevel'] ?? null,
                ],
                'is_active' => true,
                'is_temporary' => true,
                'must_change_password' => true,
                'profile_setup_complete' => false,
            ]);

            $student = Student::query()->create([
                'user_id' => $user->id,
                'school_id' => $school->id,
                'student_id_number' => $data['studentNumber'] ?? null,
                'school_name' => $school->institution_name,
                'school_subscription_code' => $school->subscription_code,
                'course' => $data['course'] ?? null,
                'year_level' => $data['yearLevel'] ?? null,
            ]);

            if ($school->organization_id) {
                $organization = Organization::query()->find($school->organization_id);
                if ($organization) {
                    $this->attachOrganizationMembership(
                        $user,
                        $organization,
                        'student_member',
                        (string) ($data['course'] ?? 'Student')
                    );
                }
            }

            return $student->load('user');
        });

        $record = $this->transformStudent($student);
        $record['defaultPassword'] = $defaultPassword;

        return response()->json([
            'message' => 'Student account created successfully.',
            'data' => $record,
        ], Response::HTTP_CREATED);
    }

    public function update(Request $request, Student $student)
    {
        $school = $this->resolveSchool($request->user());
        $this->ensureStudentBelongsToSchool($student, $school);

        $data = $request->validate([
            'studentName' => ['sometimes', 'nullable', 'string', 'max:255'],
            'studentNumber' => ['sometimes', 'nullable', 'string', 'max:255'],
            'course' => ['sometimes', 'nullable', 'string', 'max:255'],
            'yearLevel' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        if (array_key_exists('studentNumber', $data)) {
            $student->student_id_number = $data['studentNumber'];
        }
        if (array_key_exists('course', $data)) {
            $student->course = $data['course'];
        }
        if (array_key_exists('yearLevel', $data)) {
            $student->year_level = $data['yearLevel'];
        }
        $student->save();

        if ($student->user) {
            if (array_key_exists('studentName', $data) && $data['studentName']) {
                $student->user->name = $data['studentName'];
            }
            if (array_key_exists('status', $data)) {
                $student->user->is_active = $data['status'] !== 'Disabled';
            }
            $profile = $student->user->profile ?? [];
            $profile['studentNumber'] = $student->student_id_number;
            $profile['studentId'] = $student->student_id_number;
            $profile['schoolName'] = $student->school_name;
            $profile['schoolId'] = (string) $school->id;
            $profile['course'] = $student->course;
            $profile['yearLevel'] = $student->year_level;
            $student->user->profile = $profile;
            $student->user->save();
        }

        return response()->json([
            'data' => $this->transformStudent($student->fresh(['user'])),
        ]);
    }

    public function destroy(Request $request, Student $student)
    {
        $school = $this->resolveSchool($request->user());
        $this->ensureStudentBelongsToSchool($student, $school);

        $user = $student->user;
        if ($user && ! $user->is_temporary && ! $user->must_change_password) {
            return response()->json([
                'message' => 'This student has already activated the account. Disable or archive them instead of removing the roster entry.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        DB::transaction(function () use ($student, $user) {
            if ($user) {
                OrganizationMembership::query()->where('user_id', $user->id)->delete();
                $user->delete();
            } else {
                $student->delete();
            }
        });

        return response()->json(['message' => 'Student removed successfully.']);
    }

    public function export(Request $request)
    {
        $school = $this->resolveSchool($request->user());

        $rows = Student::query()
            ->with('user')
            ->where(function ($query) use ($school) {
                $query->where('school_id', $school->id);
                if ($school->subscription_code) {
                    $query->orWhere('school_subscription_code', $school->subscription_code);
                }
            })
            ->orderBy('created_at')
            ->get();

        $csv = collect([
            ['Email', 'Name', 'Student Number', 'Course', 'Year Level', 'Status'],
            ...$rows->map(function (Student $student) {
                $record = $this->transformStudent($student);
                return [
                    $record['email'],
                    $record['studentName'] ?? '',
                    $record['studentNumber'] ?? '',
                    $record['course'] ?? '',
                    $record['yearLevel'] ?? '',
                    $record['status'] ?? '',
                ];
            })->all(),
        ])->map(fn (array $row) => collect($row)->map(function ($value) {
            $escaped = str_replace('"', '""', (string) $value);
            return "\"{$escaped}\"";
        })->implode(','))
            ->implode("\n");

        return response($csv, Response::HTTP_OK, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="school-students.csv"',
        ]);
    }

    private function resolveSchool(?User $user): School
    {
        $school = $user?->school;
        if (! $school || $user?->role !== 'school') {
            abort(Response::HTTP_FORBIDDEN, 'Only schools can manage student roster data.');
        }

        return $school;
    }

    private function ensureStudentBelongsToSchool(Student $student, School $school): void
    {
        $belongs = $student->school_id === $school->id
            || ($school->subscription_code && $student->school_subscription_code === $school->subscription_code);

        if (! $belongs) {
            abort(Response::HTTP_NOT_FOUND);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function transformStudent(Student $student): array
    {
        $user = $student->user;
        $status = 'Registered';
        if ($user && ! $user->is_active) {
            $status = 'Disabled';
        } elseif ($user && $user->must_change_password) {
            $status = 'Pending Setup';
        }

        return [
            'id' => (string) $student->id,
            'schoolId' => $student->school_id ? (string) $student->school_id : '',
            'email' => $user?->email ?? '',
            'studentName' => $user?->name ?: null,
            'studentNumber' => $student->student_id_number,
            'course' => $student->course,
            'yearLevel' => $student->year_level,
            'status' => $status,
            'createdAt' => $student->created_at?->toIso8601String(),
        ];
    }

    private function generatePassword(): string
    {
        return 'Stu'.Str::upper(Str::random(3)).random_int(100, 999);
    }

    private function attachOrganizationMembership(User $user, Organization $organization, string $roleSlug, string $title = ''): void
    {
        $role = Role::query()->where('slug', $roleSlug)->first();
        if (! $role) {
            return;
        }

        OrganizationMembership::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'user_id' => $user->id,
            ],
            [
                'role_id' => $role->id,
                'status' => 'active',
                'title' => $title ?: $role->name,
            ]
        );
    }
}
