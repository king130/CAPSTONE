<?php

namespace App\Http\Controllers;

use App\Mail\StudentAccountSetupMail;
use App\Models\AccountSetupToken;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Role;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Services\SubscriptionPlanService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SchoolStudentController extends Controller
{
    public function __construct(private readonly SubscriptionPlanService $subscriptionPlans)
    {
    }

    public function index(Request $request)
    {
        $school = $this->resolveSchool($request->user());

        $students = $this->studentsForSchool($school)
            ->with($this->studentRelations())
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
        $organization = $school->organization;

        if (! $organization) {
            return response()->json(['message' => 'This school is missing an organization context.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($this->subscriptionPlans->wouldExceedAfterIncrement($organization, 'school.students')) {
            $studentOverage = $this->subscriptionPlans->getOverageForResource($organization, 'school.students') ?? [
                'key' => 'school.students',
                'message' => 'This school has reached the student account limit for the current subscription plan.',
            ];
            return response()->json([
                'message' => $studentOverage['message'],
                'overage' => $studentOverage,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

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

        $student = DB::transaction(function () use ($school, $data, $email) {
            $user = User::query()->create([
                'name' => trim((string) ($data['studentName'] ?? Str::before($email, '@'))),
                'email' => $email,
                'password' => Str::random(32).'!Aa1',
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

        $inviteDelivery = $this->sendSetupLink(
            $student->user,
            $school,
            $request->user(),
            'The account was created, but the setup email could not be sent.',
        );
        $record = $this->transformStudent($student);
        $record['inviteSent'] = $inviteDelivery['sent'];
        $record['setupLinkExpiresAt'] = $inviteDelivery['expiresAt'];
        $this->subscriptionPlans->syncOrganizationCompliance($organization);

        return response()->json([
            'message' => $inviteDelivery['sent']
                ? 'Student account created and setup email sent successfully.'
                : 'Student account created, but the setup email could not be sent.',
            'data' => $record,
            'invite' => $inviteDelivery,
        ], Response::HTTP_CREATED);
    }

    public function resendSetupLink(Request $request, Student $student)
    {
        $school = $this->resolveSchool($request->user());
        $this->ensureStudentBelongsToSchool($student, $school);

        $user = $student->user;
        if (! $user) {
            return response()->json([
                'message' => 'This student account is missing its login record.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($user->profile_setup_complete) {
            return response()->json([
                'message' => 'This student already finished account setup.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $inviteDelivery = $this->sendSetupLink($user, $school, $request->user());

        return response()->json([
            'message' => $inviteDelivery['sent']
                ? 'A fresh setup link was sent to the student email.'
                : 'The setup link was generated, but the email could not be delivered.',
            'invite' => $inviteDelivery,
        ]);
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
        $organization = $school->organization;

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

        if ($organization) {
            $this->subscriptionPlans->syncOrganizationCompliance($organization);
        }

        return response()->json(['message' => 'Student removed successfully.']);
    }

    public function export(Request $request)
    {
        $school = $this->resolveSchool($request->user());

        $rows = Student::query()
            ->select([
                'id',
                'user_id',
                'school_id',
                'intern_code',
                'student_id_number',
                'school_subscription_code',
                'course',
                'year_level',
                'created_at',
            ])
            ->with($this->studentRelations())
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
        if (! $user) {
            abort(Response::HTTP_UNAUTHORIZED);
        }

        $user->loadMissing('school:id,user_id,organization_id,institution_name,subscription_code,official_school_email');
        if ($user->role === 'school' && $user->school) {
            return $user->school;
        }

        $membership = $user->primaryOrganizationMembership();
        $organization = $membership?->organization;

        if (! $membership || ! $organization || $organization->type !== 'school') {
            abort(Response::HTTP_FORBIDDEN, 'Only schools can manage student roster data.');
        }

        $membership->loadMissing(['role.permissions', 'organization']);

        if (! $this->canManageRoster($membership)) {
            abort(Response::HTTP_FORBIDDEN, 'Only authorized school administrators can manage student roster data.');
        }

        $school = School::query()
            ->where('organization_id', $organization->id)
            ->first();

        if (! $school) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'This school organization is missing a School record.');
        }

        return $school;
    }

    private function canManageRoster(OrganizationMembership $membership): bool
    {
        $permissions = $membership->effectivePermissions();

        return in_array('manage_users', $permissions, true)
            || in_array('org.manage_members', $permissions, true)
            || in_array('org.manage_roles', $permissions, true)
            || in_array('manage_roles', $permissions, true)
            || in_array('manage_permissions', $permissions, true);
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
        } elseif ($user && ! $user->profile_setup_complete) {
            $status = 'Pending Setup';
        }

        return [
            'id' => (string) $student->id,
            'schoolId' => $student->school_id ? (string) $student->school_id : '',
            'internCode' => $student->intern_code,
            'email' => $user?->email ?? '',
            'studentName' => $user?->name ?: null,
            'studentNumber' => $student->student_id_number,
            'course' => $student->course,
            'yearLevel' => $student->year_level,
            'status' => $status,
            'createdAt' => $student->created_at?->toIso8601String(),
        ];
    }

    private function studentsForSchool(School $school)
    {
        return Student::query()
            ->select([
                'id',
                'user_id',
                'school_id',
                'intern_code',
                'student_id_number',
                'school_subscription_code',
                'course',
                'year_level',
                'created_at',
            ])
            ->where(function ($query) use ($school) {
                $query->where('school_id', $school->id);
                if ($school->subscription_code) {
                    $query->orWhere('school_subscription_code', $school->subscription_code);
                }
            });
    }

    /**
     * @return array<int, string>
     */
    private function studentRelations(): array
    {
        return [
            'user:id,name,email,is_active,must_change_password,profile_setup_complete',
        ];
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

    /**
     * @return array{sent: bool, expiresAt: string|null, errorMessage?: string}
     */
    private function sendSetupLink(User $user, School $school, User $invitedBy, string $mailFailureMessage = 'The setup email could not be sent.'): array
    {
        AccountSetupToken::query()
            ->where('user_id', $user->id)
            ->whereNull('used_at')
            ->delete();

        $plainToken = Str::random(64);
        $expiresAt = now()->addHours(48);

        AccountSetupToken::query()->create([
            'user_id' => $user->id,
            'invited_by_user_id' => $invitedBy->id,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => $expiresAt,
        ]);

        $frontendBase = rtrim((string) env('FRONTEND_URL', env('APP_URL', 'http://localhost:5173')), '/');
        $setupUrl = "{$frontendBase}/account-setup?token={$plainToken}";
        $schoolSenderEmail = $this->resolveSchoolSenderEmail($school, $invitedBy);
        $schoolSenderName = $school->institution_name ?: $invitedBy->name;

        // Gmail and most SMTP providers only allow From = the authenticated mailbox (or verified aliases).
        // Use MAIL_FROM_* as From and Reply-To for the school/inviter when they differ.
        $useSenderAsFrom = $this->shouldUseSenderAsSmtpFrom($schoolSenderEmail);

        try {
            Mail::to($user->email)->send(new StudentAccountSetupMail(
                studentName: $user->name,
                schoolName: $school->institution_name,
                setupUrl: $setupUrl,
                senderEmail: $schoolSenderEmail,
                senderName: $schoolSenderName,
                useSenderAsFrom: $useSenderAsFrom,
            ));

            return [
                'sent' => true,
                'expiresAt' => $expiresAt->toIso8601String(),
            ];
        } catch (\Throwable $exception) {
            report($exception);

            $errorMessage = $mailFailureMessage;
            $technical = $exception->getMessage();
            if (
                str_contains($technical, '535')
                || str_contains($technical, 'BadCredentials')
                || str_contains($technical, 'Failed to authenticate')
            ) {
                $errorMessage .= ' SMTP login failed. For Gmail: Google Account → Security → App passwords → generate a new 16-character password, set MAIL_PASSWORD to that value (not your normal Gmail password), then run php artisan config:clear.';
            }

            return [
                'sent' => false,
                'expiresAt' => $expiresAt->toIso8601String(),
                'errorMessage' => $errorMessage,
            ];
        }
    }

    /**
     * True only when the visible sender address matches the SMTP login (e.g. same Gmail account).
     */
    private function shouldUseSenderAsSmtpFrom(?string $senderEmail): bool
    {
        $sender = strtolower(trim((string) $senderEmail));
        if ($sender === '') {
            return false;
        }

        $smtpUser = strtolower(trim((string) config('mail.mailers.smtp.username', '')));

        return $smtpUser !== '' && $sender === $smtpUser;
    }

    private function resolveSchoolSenderEmail(School $school, User $invitedBy): ?string
    {
        $officialSchoolEmail = trim((string) ($school->official_school_email ?? ''));
        if ($officialSchoolEmail !== '' && filter_var($officialSchoolEmail, FILTER_VALIDATE_EMAIL)) {
            return $officialSchoolEmail;
        }

        $inviterEmail = trim((string) $invitedBy->email);
        if ($inviterEmail !== '' && filter_var($inviterEmail, FILTER_VALIDATE_EMAIL)) {
            return $inviterEmail;
        }

        return null;
    }
}
