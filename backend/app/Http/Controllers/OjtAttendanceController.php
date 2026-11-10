<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\OjtAttendanceEvent;
use App\Models\OjtDtrSubmission;
use App\Models\OjtLog;
use App\Models\User;
use App\Services\PermissionGate;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OjtAttendanceController extends Controller
{
    public function __construct(
        private readonly PermissionGate $permissions,
    ) {
    }

    public function index(Request $request, Application $application)
    {
        $user = $request->user();
        if (! $this->canView($user, $application)) {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $rows = OjtAttendanceEvent::query()
            ->where('application_id', $application->id)
            ->with('markedBy:id,name,email')
            ->orderByDesc('date')
            ->get();

        return response()->json([
            'data' => $rows->map(fn (OjtAttendanceEvent $row) => $this->serialize($row))->values(),
        ]);
    }

    public function store(Request $request, Application $application)
    {
        $user = $request->user();
        if (! $this->canHostManage($user, $application)) {
            return response()->json(['message' => 'Only the host supervisor can mark absences.'], Response::HTTP_FORBIDDEN);
        }
        if ($user->effectiveAppRole() !== 'admin'
            && ! $this->permissions->userCanAny($user, ['org.approve_ojt_logs', 'org.manage_applications', 'org.approve_applications'])) {
            return response()->json(['message' => 'Missing permission to mark absences.'], Response::HTTP_FORBIDDEN);
        }

        $data = $request->validate([
            'date' => ['required', 'date'],
            'type' => ['required', Rule::in(OjtAttendanceEvent::absenceTypes())],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $tz = 'Asia/Manila';
        $date = Carbon::parse($data['date'], $tz)->toDateString();

        $this->assertCanMarkAbsence($application, $date);

        $event = OjtAttendanceEvent::query()->updateOrCreate(
            [
                'application_id' => $application->id,
                'date' => $date,
            ],
            [
                'student_id' => $application->student_id,
                'type' => $data['type'],
                'reason' => trim($data['reason']),
                'marked_by_user_id' => $user->id,
            ]
        );

        $event->load('markedBy:id,name,email');

        return response()->json(['data' => $this->serialize($event)], Response::HTTP_CREATED);
    }

    public function destroy(Request $request, Application $application, OjtAttendanceEvent $attendanceEvent)
    {
        $user = $request->user();
        if ((int) $attendanceEvent->application_id !== (int) $application->id) {
            return response()->json(['message' => 'Absence does not belong to this placement.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        if (! $this->canHostManage($user, $application)) {
            return response()->json(['message' => 'Only the host supervisor can clear absences.'], Response::HTTP_FORBIDDEN);
        }
        if ($user->effectiveAppRole() !== 'admin'
            && ! $this->permissions->userCanAny($user, ['org.approve_ojt_logs', 'org.manage_applications', 'org.approve_applications'])) {
            return response()->json(['message' => 'Missing permission to clear absences.'], Response::HTTP_FORBIDDEN);
        }

        $snapshot = [
            'attendance_event_id' => $attendanceEvent->id,
            'application_id' => $attendanceEvent->application_id,
            'student_id' => $attendanceEvent->student_id,
            'date' => $attendanceEvent->date?->toDateString(),
            'type' => $attendanceEvent->type,
            'cleared_by_user_id' => $user->id,
            'cleared_by_email' => $user->email,
        ];

        $attendanceEvent->delete();

        Log::info('ojt_attendance_event_cleared', $snapshot);

        return response()->json(['message' => 'Absence cleared.']);
    }

    private function assertCanMarkAbsence(Application $application, string $date): void
    {
        $hasOpenOrApprovedLog = OjtLog::query()
            ->where('application_id', $application->id)
            ->whereDate('log_date', $date)
            ->whereIn('status', ['pending', 'approved'])
            ->exists();

        if ($hasOpenOrApprovedLog) {
            throw ValidationException::withMessages([
                'date' => ['Cannot mark this day absent while an approved or pending daily log exists for that date. Clear or reject the log first.'],
            ]);
        }

        $coveredByApprovedDtr = OjtDtrSubmission::query()
            ->where('application_id', $application->id)
            ->where('status', OjtDtrSubmission::STATUS_APPROVED)
            ->whereDate('period_start', '<=', $date)
            ->whereDate('period_end', '>=', $date)
            ->exists();

        if ($coveredByApprovedDtr) {
            throw ValidationException::withMessages([
                'date' => ['Cannot mark this day absent because it is covered by an approved DTR period.'],
            ]);
        }
    }

    private function canView(User $user, Application $application): bool
    {
        $appRole = $user->effectiveAppRole();
        if ($appRole === 'admin') {
            return true;
        }
        if ($appRole === 'student') {
            return $user->student && (int) $application->student_id === (int) $user->student->id;
        }
        if ($appRole === 'company') {
            return $this->canHostManage($user, $application);
        }
        if ($appRole === 'school') {
            $school = $user->organizationSchool() ?? $user->school;
            $application->loadMissing('student');
            if (! $school || ! $application->student) {
                return false;
            }
            if ((int) $application->student->school_id === (int) $school->id) {
                return true;
            }
            if ($school->subscription_code && $application->student->school_subscription_code === $school->subscription_code) {
                return true;
            }
        }

        return false;
    }

    private function canHostManage(User $user, Application $application): bool
    {
        $appRole = $user->effectiveAppRole();
        if ($appRole === 'admin') {
            return true;
        }
        $company = $user->organizationCompany() ?? $user->company;
        if ($appRole === 'company' && $company) {
            return (int) $application->company_id === (int) $company->id;
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(OjtAttendanceEvent $row): array
    {
        return [
            'id' => (int) $row->id,
            'applicationId' => (string) $row->application_id,
            'studentId' => (int) $row->student_id,
            'date' => $row->date?->toDateString(),
            'type' => $row->type,
            'reason' => $row->reason,
            'markedByUserId' => (string) $row->marked_by_user_id,
            'markedByName' => $row->markedBy?->name,
            'createdAt' => $row->created_at?->toIso8601String(),
            'updatedAt' => $row->updated_at?->toIso8601String(),
        ];
    }
}
