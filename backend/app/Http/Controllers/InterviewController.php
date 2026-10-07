<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\ApplicationInterview;
use App\Models\Notification;
use App\Services\PermissionGate;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class InterviewController extends Controller
{
    /**
     * Application statuses that may receive interview proposals.
     * School endorsement is required; company acceptance is not.
     *
     * @var list<string>
     */
    private const PROPOSABLE_APPLICATION_STATUSES = ['endorsed', 'accepted'];

    public function __construct(private readonly PermissionGate $permissions)
    {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $appRole = $user->effectiveAppRole();

        $query = ApplicationInterview::query()
            ->with([
                'application:id,internship_id,student_id,company_id,status,internship_title,student_name,student_email',
                'application.student:id,user_id,school_id,school_name',
                'application.company:id,user_id,company_name',
            ])
            ->orderByDesc('scheduled_at');

        if ($request->filled('application_id')) {
            $query->where('application_id', $request->integer('application_id'));
        }

        $company = $user->organizationCompany() ?? $user->company;
        $school = $user->organizationSchool() ?? $user->school;

        if ($appRole === 'student' && $user->student) {
            $query->whereHas('application', fn ($q) => $q->where('student_id', $user->student->id));
        } elseif ($appRole === 'company' && $company) {
            if (! $this->permissions->userCanAny($user, ['org.view_applications', 'org.approve_applications', 'org.manage_applications'])) {
                return response()->json(['message' => 'Missing permission to view interviews.'], Response::HTTP_FORBIDDEN);
            }
            $query->whereHas('application', fn ($q) => $q->where('company_id', $company->id));
        } elseif ($appRole === 'school' && $school) {
            if (! $this->permissions->userCanAny($user, ['org.view_applications', 'org.approve_applications', 'org.manage_applications'])) {
                return response()->json(['message' => 'Missing permission to view interviews.'], Response::HTTP_FORBIDDEN);
            }
            $query->whereHas('application.student', function ($studentQuery) use ($school) {
                $studentQuery->where('school_id', $school->id);
                if ($school->subscription_code) {
                    $studentQuery->orWhere('school_subscription_code', $school->subscription_code);
                }
            });
        } elseif ($appRole !== 'admin') {
            return response()->json(['data' => []]);
        }

        return response()->json([
            'data' => $query->get()->map(fn (ApplicationInterview $interview) => $this->serialize($interview)),
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $appRole = $user->effectiveAppRole();

        if ($appRole === 'student') {
            return response()->json(['message' => 'Students cannot propose interviews.'], Response::HTTP_FORBIDDEN);
        }

        if ($appRole !== 'admin' && ! $this->permissions->userCanAny($user, ['org.approve_applications', 'org.manage_applications'])) {
            return response()->json(['message' => 'Missing permission to propose interviews.'], Response::HTTP_FORBIDDEN);
        }

        $data = $request->validate([
            'application_id' => ['required', 'integer', 'exists:applications,id'],
            'scheduled_at' => ['required', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'min:15', 'max:480'],
            'mode' => ['required', 'in:onsite,online'],
            'location_or_link' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string'],
        ]);

        $application = Application::query()->with([
            'student.school',
            'company',
        ])->findOrFail($data['application_id']);

        if (! $this->canAccessApplication($user, $application)) {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $applicationStatus = strtolower(trim((string) $application->status));
        if (! in_array($applicationStatus, self::PROPOSABLE_APPLICATION_STATUSES, true)) {
            return response()->json([
                'message' => 'Interviews can only be proposed after the application has been endorsed by the school.',
                'code' => 'application_not_endorsed',
                'errors' => [
                    'application_id' => ['Interviews can only be proposed after the application has been endorsed by the school.'],
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $interview = ApplicationInterview::query()->create([
            'application_id' => $application->id,
            'scheduled_at' => $data['scheduled_at'],
            'duration_minutes' => $data['duration_minutes'] ?? 60,
            'mode' => $data['mode'],
            'location_or_link' => $data['location_or_link'] ?? null,
            'status' => 'proposed',
            'notes' => $data['notes'] ?? null,
            'created_by_user_id' => $user->id,
        ]);

        $interview->load([
            'application:id,internship_id,student_id,company_id,status,internship_title,student_name,student_email',
            'application.student:id,user_id,school_id,school_name',
            'application.student.school:id,user_id,institution_name',
            'application.company:id,user_id,company_name',
        ]);

        $this->notifyInterviewProposed($interview, (int) $user->id);

        return response()->json(['data' => $this->serialize($interview)], Response::HTTP_CREATED);
    }

    public function confirm(Request $request, ApplicationInterview $interview)
    {
        $user = $request->user();
        $interview->loadMissing([
            'application.student.school',
            'application.company',
        ]);

        if (! $this->canAccessApplication($user, $interview->application)) {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        if ($interview->status !== 'proposed') {
            return response()->json(['message' => 'Only proposed interviews can be confirmed.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $interview->update(['status' => 'confirmed']);

        $fresh = $interview->fresh([
            'application:id,internship_id,student_id,company_id,status,internship_title,student_name,student_email',
            'application.student:id,user_id,school_id,school_name',
            'application.student.school:id,user_id,institution_name',
            'application.company:id,user_id,company_name',
        ]);

        $this->notifyInterviewConfirmed($fresh, (int) $user->id);

        return response()->json(['data' => $this->serialize($fresh)]);
    }

    public function cancel(Request $request, ApplicationInterview $interview)
    {
        $user = $request->user();
        $interview->loadMissing([
            'application.student.school',
            'application.company',
        ]);

        if (! $this->canAccessApplication($user, $interview->application)) {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        if (in_array($interview->status, ['cancelled', 'completed'], true)) {
            return response()->json(['message' => 'This interview can no longer be cancelled.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $data = $request->validate([
            'notes' => ['nullable', 'string'],
        ]);

        $interview->update([
            'status' => 'cancelled',
            'notes' => $data['notes'] ?? $interview->notes,
        ]);

        $fresh = $interview->fresh([
            'application:id,internship_id,student_id,company_id,status,internship_title,student_name,student_email',
            'application.student:id,user_id,school_id,school_name',
            'application.student.school:id,user_id,institution_name',
            'application.company:id,user_id,company_name',
        ]);

        $this->notifyInterviewCancelled($fresh, (int) $user->id);

        return response()->json(['data' => $this->serialize($fresh)]);
    }

    private function canAccessApplication($user, Application $application): bool
    {
        $appRole = $user->effectiveAppRole();
        if ($appRole === 'admin') {
            return true;
        }

        if ($appRole === 'student' && $user->student) {
            return (int) $application->student_id === (int) $user->student->id;
        }

        $company = $user->organizationCompany() ?? $user->company;
        if ($appRole === 'company' && $company) {
            return (int) $application->company_id === (int) $company->id;
        }

        $school = $user->organizationSchool() ?? $user->school;
        if ($appRole === 'school' && $school && $application->student) {
            if ((int) $application->student->school_id === (int) $school->id) {
                return true;
            }
            if ($school->subscription_code && $application->student->school_subscription_code === $school->subscription_code) {
                return true;
            }
        }

        return false;
    }

    private function notifyInterviewProposed(ApplicationInterview $interview, int $actorUserId): void
    {
        $application = $interview->application;
        if (! $application) {
            return;
        }

        $title = $application->internship_title ?: 'internship application';
        $when = $interview->scheduled_at?->timezone(config('app.timezone'))->format('M j, Y g:i A');
        $body = $when
            ? 'An interview was proposed for '.$title.' on '.$when.'.'
            : 'An interview was proposed for '.$title.'.';

        $studentUserId = $application->student?->user_id;
        if ($studentUserId && (int) $studentUserId !== $actorUserId) {
            $this->createInAppNotification(
                (int) $studentUserId,
                'Interview proposed',
                $body,
                [
                    'type' => 'interview',
                    'interviewId' => (string) $interview->id,
                    'applicationId' => (string) $application->id,
                    'redirectTo' => '/intern/applications',
                ]
            );
        }
    }

    private function notifyInterviewConfirmed(ApplicationInterview $interview, int $actorUserId): void
    {
        $application = $interview->application;
        if (! $application) {
            return;
        }

        $title = $application->internship_title ?: 'internship application';
        $when = $interview->scheduled_at?->timezone(config('app.timezone'))->format('M j, Y g:i A');
        $body = $when
            ? 'The interview for '.$title.' on '.$when.' was confirmed.'
            : 'The interview for '.$title.' was confirmed.';

        foreach ($this->interviewCounterpartyUserIds($interview, $actorUserId) as $userId) {
            $this->createInAppNotification(
                $userId,
                'Interview confirmed',
                $body,
                $this->interviewNotificationMetadata($interview, $userId)
            );
        }
    }

    private function notifyInterviewCancelled(ApplicationInterview $interview, int $actorUserId): void
    {
        $application = $interview->application;
        if (! $application) {
            return;
        }

        $title = $application->internship_title ?: 'internship application';
        $when = $interview->scheduled_at?->timezone(config('app.timezone'))->format('M j, Y g:i A');
        $body = $when
            ? 'The interview for '.$title.' on '.$when.' was cancelled.'
            : 'The interview for '.$title.' was cancelled.';

        foreach ($this->interviewCounterpartyUserIds($interview, $actorUserId) as $userId) {
            $this->createInAppNotification(
                $userId,
                'Interview cancelled',
                $body,
                $this->interviewNotificationMetadata($interview, $userId)
            );
        }
    }

    /**
     * @return list<int>
     */
    private function interviewCounterpartyUserIds(ApplicationInterview $interview, int $actorUserId): array
    {
        $application = $interview->application;
        if (! $application) {
            return [];
        }

        $ids = collect([
            $application->student?->user_id,
            $application->company?->user_id,
            $application->student?->school?->user_id,
        ])
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->reject(fn (int $id) => $id === $actorUserId)
            ->values()
            ->all();

        return $ids;
    }

    /**
     * @return array<string, mixed>
     */
    private function interviewNotificationMetadata(ApplicationInterview $interview, int $recipientUserId): array
    {
        $application = $interview->application;
        $redirectTo = '/intern/applications';

        if ($application?->company?->user_id && (int) $application->company->user_id === $recipientUserId) {
            $redirectTo = '/dashboard/applicants';
        } elseif ($application?->student?->school?->user_id && (int) $application->student->school->user_id === $recipientUserId) {
            $redirectTo = '/school/endorsements';
        }

        return [
            'type' => 'interview',
            'interviewId' => (string) $interview->id,
            'applicationId' => (string) ($application?->id ?? ''),
            'redirectTo' => $redirectTo,
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function createInAppNotification(int $userId, string $title, string $body, array $metadata): void
    {
        Notification::query()->create([
            'user_id' => $userId,
            'title' => $title,
            'body' => $body,
            'channel' => 'in_app',
            'metadata' => $metadata,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(ApplicationInterview $interview): array
    {
        return [
            'id' => (string) $interview->id,
            'applicationId' => (string) $interview->application_id,
            'scheduledAt' => $interview->scheduled_at?->toIso8601String(),
            'durationMinutes' => (int) $interview->duration_minutes,
            'mode' => $interview->mode,
            'locationOrLink' => $interview->location_or_link,
            'status' => $interview->status,
            'notes' => $interview->notes,
            'createdByUserId' => (string) $interview->created_by_user_id,
            'application' => $interview->application ? [
                'id' => (string) $interview->application->id,
                'internshipTitle' => $interview->application->internship_title,
                'studentName' => $interview->application->student_name,
                'studentEmail' => $interview->application->student_email,
                'status' => $interview->application->status,
                'companyName' => $interview->application->company?->company_name,
            ] : null,
            'createdAt' => $interview->created_at?->toIso8601String(),
            'updatedAt' => $interview->updated_at?->toIso8601String(),
        ];
    }
}
