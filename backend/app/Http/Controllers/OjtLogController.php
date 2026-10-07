<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Notification;
use App\Models\OjtLog;
use App\Models\Organization;
use App\Models\Student;
use App\Services\PermissionGate;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

class OjtLogController extends Controller
{
    public const DEFAULT_REQUIRED_HOURS = 500;

    public function __construct(private readonly PermissionGate $permissions)
    {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $appRole = $user->effectiveAppRole();

        $query = OjtLog::query()
            ->with([
                'student:id,user_id,school_id,school_name,organization_id',
                'student.user:id,name,email',
                'application:id,internship_title,company_id,status',
                'application.company:id,company_name',
                'internship:id,title,company_name,host_name',
            ])
            ->orderByDesc('log_date')
            ->orderByDesc('time_in');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('student_id')) {
            $query->where('student_id', $request->integer('student_id'));
        }
        if ($request->filled('application_id')) {
            $query->where('application_id', $request->integer('application_id'));
        }
        if ($request->filled('date_from')) {
            $query->whereDate('log_date', '>=', $request->string('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('log_date', '<=', $request->string('date_to'));
        }

        $company = $user->organizationCompany() ?? $user->company;
        $school = $user->organizationSchool() ?? $user->school;

        if ($appRole === 'student' && $user->student) {
            $query->where('student_id', $user->student->id);
        } elseif ($appRole === 'company' && $company) {
            $query->whereHas('application', fn ($q) => $q->where('company_id', $company->id));
        } elseif ($appRole === 'school' && $school) {
            if (! $this->permissions->userCanAny($user, ['org.view_ojt_progress', 'org.approve_ojt_logs', 'org.view_students'])) {
                return response()->json(['message' => 'Missing permission to view OJT logs.'], Response::HTTP_FORBIDDEN);
            }
            $query->whereHas('student', function ($studentQuery) use ($school) {
                $studentQuery->where('school_id', $school->id);
                if ($school->subscription_code) {
                    $studentQuery->orWhere('school_subscription_code', $school->subscription_code);
                }
            });
        } elseif ($appRole !== 'admin') {
            return response()->json(['data' => []]);
        }

        return response()->json([
            'data' => $query->get()->map(fn (OjtLog $log) => $this->serialize($log)),
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        if ($user->effectiveAppRole() !== 'student' || ! $user->student) {
            return response()->json(['message' => 'Only students can submit OJT logs.'], Response::HTTP_FORBIDDEN);
        }

        $student = $user->student;

        $data = $request->validate([
            'date' => ['required', 'date'],
            'timeIn' => ['required', 'date_format:H:i'],
            'timeOut' => ['required', 'date_format:H:i', 'after:timeIn'],
            'tasksDone' => ['required', 'string'],
            'application_id' => ['nullable', 'integer', 'exists:applications,id'],
            'internship_id' => ['nullable', 'integer', 'exists:internships,id'],
            'mood' => ['nullable', 'string', 'max:50'],
        ]);

        $application = $this->resolveAcceptedApplicationForStudent(
            $student,
            isset($data['application_id']) ? (int) $data['application_id'] : null,
            isset($data['internship_id']) ? (int) $data['internship_id'] : null,
        );

        if ($application instanceof \Illuminate\Http\JsonResponse) {
            return $application;
        }

        $hours = $this->hoursBetween($data['timeIn'], $data['timeOut']);
        $organizationId = $student->organization_id
            ?? $user->activeOrganization()?->id
            ?? $application->company?->organization_id;

        $log = OjtLog::query()->create([
            'student_id' => $student->id,
            'application_id' => $application->id,
            'internship_id' => $application->internship_id,
            'organization_id' => $organizationId,
            'log_date' => $data['date'],
            'time_in' => $data['timeIn'],
            'time_out' => $data['timeOut'],
            'hours' => $hours,
            'tasks' => $data['tasksDone'],
            'status' => 'pending',
        ]);

        $log->load([
            'student:id,user_id,school_id,school_name,organization_id',
            'student.user:id,name,email',
            'application:id,internship_title,company_id,status',
            'application.company:id,company_name',
            'internship:id,title,company_name,host_name',
        ]);

        return response()->json(['data' => $this->serialize($log)], Response::HTTP_CREATED);
    }

    public function update(Request $request, OjtLog $ojtLog)
    {
        $user = $request->user();
        if ($user->effectiveAppRole() !== 'student' || ! $user->student || (int) $ojtLog->student_id !== (int) $user->student->id) {
            return response()->json(['message' => 'You can only edit your own OJT logs.'], Response::HTTP_FORBIDDEN);
        }

        if ($ojtLog->status !== 'pending') {
            return response()->json(['message' => 'Only pending logs can be updated.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $data = $request->validate([
            'date' => ['required', 'date'],
            'timeIn' => ['required', 'date_format:H:i'],
            'timeOut' => ['required', 'date_format:H:i', 'after:timeIn'],
            'tasksDone' => ['required', 'string'],
        ]);

        $ojtLog->update([
            'log_date' => $data['date'],
            'time_in' => $data['timeIn'],
            'time_out' => $data['timeOut'],
            'hours' => $this->hoursBetween($data['timeIn'], $data['timeOut']),
            'tasks' => $data['tasksDone'],
        ]);

        return response()->json(['data' => $this->serialize($ojtLog->fresh([
            'student:id,user_id,school_id,school_name,organization_id',
            'student.user:id,name,email',
            'application:id,internship_title,company_id,status',
            'application.company:id,company_name',
            'internship:id,title,company_name,host_name',
        ]))]);
    }

    public function destroy(Request $request, OjtLog $ojtLog)
    {
        $user = $request->user();
        if ($user->effectiveAppRole() !== 'student' || ! $user->student || (int) $ojtLog->student_id !== (int) $user->student->id) {
            return response()->json(['message' => 'You can only delete your own OJT logs.'], Response::HTTP_FORBIDDEN);
        }

        if ($ojtLog->status !== 'pending') {
            return response()->json(['message' => 'Only pending logs can be deleted.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $ojtLog->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    public function updateStatus(Request $request, OjtLog $ojtLog)
    {
        $user = $request->user();
        $appRole = $user->effectiveAppRole();

        if ($appRole !== 'admin' && ! $this->permissions->userCan($user, 'org.approve_ojt_logs')) {
            return response()->json(['message' => 'Missing permission to approve OJT logs.'], Response::HTTP_FORBIDDEN);
        }

        if (! $this->canReviewLog($user, $ojtLog)) {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        if ($ojtLog->status !== 'pending') {
            return response()->json(['message' => 'Only pending logs can be reviewed.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $data = $request->validate([
            'status' => ['required', 'in:approved,rejected'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($data['status'] === 'rejected' && empty($data['reason'])) {
            return response()->json(['message' => 'A rejection reason is required.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $previousStatus = (string) $ojtLog->status;
        $nextStatus = (string) $data['status'];

        $ojtLog->update([
            'status' => $nextStatus,
            'reviewed_by_user_id' => $user->id,
            'reviewed_at' => now(),
            'rejection_reason' => $nextStatus === 'rejected' ? ($data['reason'] ?? null) : null,
        ]);

        if ($previousStatus !== $nextStatus) {
            $ojtLog->loadMissing(['student:id,user_id']);
            $studentUserId = $ojtLog->student?->user_id;
            if ($studentUserId) {
                $logDate = $ojtLog->log_date?->toDateString() ?? 'your OJT log';
                if ($nextStatus === 'approved') {
                    Notification::query()->create([
                        'user_id' => (int) $studentUserId,
                        'title' => 'OJT log approved',
                        'body' => 'Your OJT log for '.$logDate.' was approved.',
                        'channel' => 'in_app',
                        'metadata' => [
                            'type' => 'hours',
                            'ojtLogId' => (string) $ojtLog->id,
                            'redirectTo' => '/ojt-hours',
                        ],
                    ]);
                } elseif ($nextStatus === 'rejected') {
                    Notification::query()->create([
                        'user_id' => (int) $studentUserId,
                        'title' => 'OJT log rejected',
                        'body' => 'Your OJT log for '.$logDate.' was rejected.',
                        'channel' => 'in_app',
                        'metadata' => [
                            'type' => 'hours',
                            'ojtLogId' => (string) $ojtLog->id,
                            'redirectTo' => '/ojt-hours',
                        ],
                    ]);
                }
            }
        }

        return response()->json(['data' => $this->serialize($ojtLog->fresh([
            'student:id,user_id,school_id,school_name,organization_id',
            'student.user:id,name,email',
            'application:id,internship_title,company_id,status',
            'application.company:id,company_name',
            'internship:id,title,company_name,host_name',
        ]))]);
    }

    public function requiredHoursSetting(Request $request)
    {
        $user = $request->user();
        $appRole = $user->effectiveAppRole();

        if (! in_array($appRole, ['school', 'company'], true)) {
            return response()->json([
                'message' => 'Only schools and companies manage OJT required hours.',
            ], Response::HTTP_FORBIDDEN);
        }

        $organization = $user->activeOrganization();
        if (! $organization) {
            return response()->json(['message' => 'No active organization context found.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json([
            'data' => [
                'requiredHours' => self::requiredHoursForOrg($organization),
            ],
        ]);
    }

    public function updateRequiredHoursSetting(Request $request)
    {
        $user = $request->user();
        $appRole = $user->effectiveAppRole();

        if (! in_array($appRole, ['school', 'company'], true)) {
            return response()->json([
                'message' => 'Only schools and companies manage OJT required hours.',
            ], Response::HTTP_FORBIDDEN);
        }

        if (! $this->permissions->userCanAny($user, ['org.manage_profile', 'manage_profile', 'org.approve_ojt_logs'])) {
            return response()->json(['message' => 'Missing permission to update OJT required hours.'], Response::HTTP_FORBIDDEN);
        }

        $organization = $user->activeOrganization();
        if (! $organization) {
            return response()->json(['message' => 'No active organization context found.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $data = $request->validate([
            'requiredHours' => ['required', 'integer', 'min:1', 'max:10000'],
        ]);

        $settings = is_array($organization->settings) ? $organization->settings : [];
        $settings['required_ojt_hours'] = (int) $data['requiredHours'];
        unset($settings['requiredHours']);
        $organization->settings = $settings;
        $organization->save();

        return response()->json([
            'data' => [
                'requiredHours' => self::requiredHoursForOrg($organization->fresh()),
            ],
        ]);
    }

    public function progress(Request $request)
    {
        $user = $request->user();
        $appRole = $user->effectiveAppRole();

        if ($appRole === 'student' && $user->student) {
            return response()->json(['data' => $this->progressForStudent($user->student)]);
        }

        if ($appRole !== 'admin' && ! $this->permissions->userCan($user, 'org.view_ojt_progress')) {
            return response()->json(['message' => 'Missing permission to view OJT progress.'], Response::HTTP_FORBIDDEN);
        }

        $school = $user->organizationSchool() ?? $user->school;
        $company = $user->organizationCompany() ?? $user->company;

        $studentsQuery = Student::query()->with('user:id,name,email');

        if ($appRole === 'school' && $school) {
            $studentsQuery->where(function ($q) use ($school) {
                $q->where('school_id', $school->id);
                if ($school->subscription_code) {
                    $q->orWhere('school_subscription_code', $school->subscription_code);
                }
            });
        } elseif ($appRole === 'company' && $company) {
            $studentsQuery->whereHas('applications', fn ($q) => $q->where('company_id', $company->id)->where('status', 'accepted'));
        } elseif ($appRole !== 'admin') {
            return response()->json(['data' => []]);
        }

        $requiredHours = $this->requiredHoursForOrg($user->activeOrganization());

        $summaries = $studentsQuery->get()->map(function (Student $student) use ($requiredHours) {
            $logs = OjtLog::query()->where('student_id', $student->id)->get();
            $approved = (float) $logs->where('status', 'approved')->sum('hours');
            $totalLogged = (float) $logs->sum('hours');
            $percent = $requiredHours > 0 ? ($approved / $requiredHours) * 100 : 0;
            $status = $approved >= $requiredHours && $requiredHours > 0
                ? 'completed'
                : ($approved <= 0 && $totalLogged <= 0 ? 'not_started' : ($percent >= 60 ? 'on_track' : 'at_risk'));

            $acceptedApp = Application::query()
                ->with('company:id,company_name')
                ->where('student_id', $student->id)
                ->where('status', 'accepted')
                ->orderByDesc('updated_at')
                ->first();

            return [
                'internId' => (int) $student->id,
                'internName' => $student->user?->name ?? $student->user?->email ?? 'Intern',
                'company' => $acceptedApp?->company?->company_name ?? '',
                'school' => $student->school_name ?? '',
                'approvedHours' => round($approved, 2),
                'requiredHours' => $requiredHours,
                'status' => $status,
                'totalLoggedHours' => round($totalLogged, 2),
            ];
        })->values();

        return response()->json(['data' => $summaries]);
    }

    /**
     * @return array<string, mixed>
     */
    private function progressForStudent(Student $student): array
    {
        $logs = OjtLog::query()->where('student_id', $student->id)->get();
        $requiredHours = $this->requiredHoursForOrg(
            $student->organization_id
                ? Organization::query()->find($student->organization_id)
                : null
        );

        $totalLogged = (float) $logs->sum('hours');
        $totalApproved = (float) $logs->where('status', 'approved')->sum('hours');
        $totalPending = (float) $logs->where('status', 'pending')->sum('hours');
        $totalRejected = (float) $logs->where('status', 'rejected')->sum('hours');

        return [
            'totalLogged' => round($totalLogged, 2),
            'totalApproved' => round($totalApproved, 2),
            'totalPending' => round($totalPending, 2),
            'totalRejected' => round($totalRejected, 2),
            'requiredHours' => $requiredHours,
            'percentComplete' => $requiredHours > 0 ? min(100, ($totalApproved / $requiredHours) * 100) : 0,
        ];
    }

    /**
     * Resolve an accepted application for OJT logging.
     * When application_id is omitted, uses the existing latest-accepted-by-updated_at rule.
     *
     * @return Application|\Illuminate\Http\JsonResponse
     */
    private function resolveAcceptedApplicationForStudent(
        Student $student,
        ?int $applicationId,
        ?int $internshipId,
    ) {
        if ($applicationId !== null) {
            $application = Application::query()
                ->with('company:id,organization_id,company_name')
                ->where('id', $applicationId)
                ->where('student_id', $student->id)
                ->first();

            if (! $application) {
                return response()->json([
                    'message' => 'Application not found for this student.',
                    'code' => 'application_not_found',
                    'errors' => [
                        'application_id' => ['Application not found for this student.'],
                    ],
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            if (strtolower(trim((string) $application->status)) !== 'accepted') {
                return response()->json([
                    'message' => 'OJT logs can only be submitted for an accepted internship application.',
                    'code' => 'application_not_accepted',
                    'errors' => [
                        'application_id' => ['OJT logs can only be submitted for an accepted internship application.'],
                    ],
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        } else {
            $application = Application::query()
                ->with('company:id,organization_id,company_name')
                ->where('student_id', $student->id)
                ->where('status', 'accepted')
                ->orderByDesc('updated_at')
                ->first();

            if (! $application) {
                return response()->json([
                    'message' => 'You need an accepted internship application before logging OJT hours.',
                    'code' => 'accepted_application_required',
                    'errors' => [
                        'application_id' => ['You need an accepted internship application before logging OJT hours.'],
                    ],
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        if ($internshipId !== null && (int) $application->internship_id !== $internshipId) {
            return response()->json([
                'message' => 'The internship does not match the selected accepted application.',
                'code' => 'internship_application_mismatch',
                'errors' => [
                    'internship_id' => ['The internship does not match the selected accepted application.'],
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $application;
    }

    private function canReviewLog($user, OjtLog $ojtLog): bool
    {
        $appRole = $user->effectiveAppRole();
        if ($appRole === 'admin') {
            return true;
        }

        $ojtLog->loadMissing(['application', 'student']);

        $company = $user->organizationCompany() ?? $user->company;
        if ($appRole === 'company' && $company && $ojtLog->application) {
            return (int) $ojtLog->application->company_id === (int) $company->id;
        }

        $school = $user->organizationSchool() ?? $user->school;
        if ($appRole === 'school' && $school && $ojtLog->student) {
            if ((int) $ojtLog->student->school_id === (int) $school->id) {
                return true;
            }
            if ($school->subscription_code && $ojtLog->student->school_subscription_code === $school->subscription_code) {
                return true;
            }
        }

        return false;
    }

    public static function requiredHoursForOrg(?Organization $organization): int
    {
        if (! $organization) {
            return self::DEFAULT_REQUIRED_HOURS;
        }

        $settings = $organization->settings ?? [];
        $value = $settings['required_ojt_hours'] ?? $settings['requiredHours'] ?? self::DEFAULT_REQUIRED_HOURS;

        return max(1, (int) $value);
    }

    private function hoursBetween(string $timeIn, string $timeOut): float
    {
        $in = Carbon::createFromFormat('H:i', $timeIn);
        $out = Carbon::createFromFormat('H:i', $timeOut);
        if ($out->lessThanOrEqualTo($in)) {
            return 0;
        }

        return round($in->diffInMinutes($out) / 60, 2);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(OjtLog $log): array
    {
        $studentName = $log->student?->user?->name ?? $log->student?->user?->email ?? 'Intern';

        return [
            'id' => (int) $log->id,
            'internId' => (int) $log->student_id,
            'internName' => $studentName,
            'date' => $log->log_date?->toDateString(),
            'timeIn' => substr((string) $log->time_in, 0, 5),
            'timeOut' => substr((string) $log->time_out, 0, 5),
            'hoursRendered' => (float) $log->hours,
            'tasksDone' => $log->tasks,
            'status' => $log->status,
            'rejectionReason' => $log->rejection_reason,
            'createdAt' => $log->created_at?->toIso8601String(),
            'submittedAt' => $log->created_at?->toIso8601String(),
            'company' => $log->application?->company?->company_name
                ?? $log->internship?->company_name
                ?? $log->internship?->host_name
                ?? '',
            'school' => $log->student?->school_name ?? '',
            'applicationId' => $log->application_id ? (string) $log->application_id : null,
            'internshipId' => $log->internship_id ? (string) $log->internship_id : null,
            'reviewedAt' => $log->reviewed_at?->toIso8601String(),
        ];
    }
}
