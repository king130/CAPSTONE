<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Internship;
use App\Models\Notification;
use App\Services\InternshipEligibilityService;
use App\Services\PermissionGate;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ApplicationController extends Controller
{
    public function __construct(
        private readonly PermissionGate $permissions,
        private readonly InternshipEligibilityService $internshipEligibility,
    ) {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $appRole = $user->effectiveAppRole();
        if (
            $appRole !== 'admin'
            && $appRole !== 'student'
            && ! $this->permissions->userCanAny($user, ['org.view_applications', 'org.approve_applications', 'org.manage_applications'])
        ) {
            return response()->json(['message' => 'Missing permission to view applications.'], Response::HTTP_FORBIDDEN);
        }
        $query = Application::query()
            ->select([
                'id',
                'internship_id',
                'student_id',
                'company_id',
                'status',
                'resume_url',
                'documents',
                'documents_pending',
                'internship_title',
                'student_name',
                'student_email',
                'student_course',
                'created_at',
                'updated_at',
            ])
            ->with([
                'student:id,user_id,school_id,school_subscription_code,school_name',
                'internship:id,company_id,title',
                'internship.company:id,user_id,company_name',
            ]);

        $company = $user->organizationCompany() ?? $user->company;
        $school = $user->organizationSchool() ?? $user->school;

        if ($appRole === 'student' && $user->student) {
            $query->where('student_id', $user->student->id);
        } elseif ($appRole === 'company' && $company) {
            $query->where('company_id', $company->id);
        } elseif ($appRole === 'school' && $school) {
            $query->whereHas('student', function ($studentQuery) use ($school) {
                $studentQuery->where('school_id', $school->id);
                if ($school->subscription_code) {
                    $studentQuery->orWhere('school_subscription_code', $school->subscription_code);
                }
            });
        } elseif ($appRole === 'admin') {
            // list all
        } else {
            return response()->json(['data' => []]);
        }

        $items = $query
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($application) => $this->serialize($application));

        return response()->json(['data' => $items]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        if ($user->effectiveAppRole() !== 'student' || ! $user->student) {
            return response()->json(['message' => 'Only students can apply.'], Response::HTTP_FORBIDDEN);
        }

        $data = $request->validate([
            'internship_id' => ['required', 'integer', 'exists:internships,id'],
            'resume_url' => ['nullable', 'string', 'max:2048'],
            'documents' => ['nullable', 'array'],
            'cover_letter' => ['nullable', 'string'],
        ]);

        $internship = Internship::query()->with('company')->findOrFail($data['internship_id']);

        $student = $user->student;
        $eligibility = $this->internshipEligibility->evaluate($student, $internship);
        if (! $eligibility['allowed']) {
            return response()->json([
                'message' => $eligibility['message'],
                'code' => $eligibility['code'],
                'errors' => [
                    'internship_id' => [$eligibility['message']],
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $companyId = $internship->company_id;

        $application = Application::query()->create([
            'internship_id' => $internship->id,
            'student_id' => $student->id,
            'company_id' => $companyId,
            'status' => 'submitted',
            'cover_letter' => $data['cover_letter'] ?? null,
            'resume_url' => $data['resume_url'] ?? null,
            'documents' => $data['documents'] ?? null,
            'documents_pending' => false,
            'internship_title' => $internship->title,
            'student_name' => $user->name,
            'student_email' => $user->email,
            'student_course' => $student->course,
        ]);

        $application->load([
            'student:id,user_id,school_id,school_subscription_code,school_name',
            'student.school:id,user_id,institution_name',
            'internship:id,company_id,title',
            'internship.company:id,user_id,company_name',
        ]);

        $schoolUserId = $application->student?->school?->user_id;
        if ($schoolUserId) {
            $this->createInAppNotification(
                (int) $schoolUserId,
                'New internship application',
                trim(($application->student_name ?: 'A student').' submitted an application for '.($application->internship_title ?: 'an internship').'.'),
                [
                    'type' => 'application',
                    'applicationId' => (string) $application->id,
                    'redirectTo' => '/school/endorsements',
                ]
            );
        }

        return response()->json(['data' => $this->serialize($application)], Response::HTTP_CREATED);
    }

    public function updateStatus(Request $request, Application $application)
    {
        $user = $request->user();
        $appRole = $user->effectiveAppRole();
        $internship = $application->internship;

        $company = $user->organizationCompany() ?? $user->company;
        $school = $user->organizationSchool() ?? $user->school;

        $canCompany = $appRole === 'company'
            && $company
            && $internship
            && $internship->company_id === $company->id;

        $canSchool = $appRole === 'school'
            && $school
            && $application->student
            && (
                $application->student->school_id === $school->id
                || ($school->subscription_code && $application->student->school_subscription_code === $school->subscription_code)
            );

        $canStudent = $appRole === 'student'
            && $user->student
            && $application->student_id === $user->student->id;

        if (! $canCompany && ! $canSchool && ! $canStudent && $appRole !== 'admin') {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $data = $request->validate([
            'status' => ['required', 'string', 'max:64'],
        ]);

        $nextStatus = strtolower(trim((string) $data['status']));
        $currentStatus = strtolower(trim((string) $application->status));

        if ($appRole === 'school') {
            if (! $this->permissions->userCan($user, 'org.approve_applications')) {
                return response()->json(['message' => 'Missing permission to approve applications.'], Response::HTTP_FORBIDDEN);
            }
            if (! in_array($nextStatus, ['endorsed', 'school_rejected'], true)) {
                return response()->json(['message' => 'Schools can only endorse or reject applications.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            if (! in_array($currentStatus, ['submitted', 'pending'], true)) {
                return response()->json(['message' => 'Only newly submitted applications can be endorsed by the school.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        if ($appRole === 'company') {
            if (! $this->permissions->userCan($user, 'org.approve_applications')) {
                return response()->json(['message' => 'Missing permission to approve applications.'], Response::HTTP_FORBIDDEN);
            }
            if (! in_array($nextStatus, ['accepted', 'rejected'], true)) {
                return response()->json(['message' => 'Companies can only accept or reject endorsed applications.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            if ($currentStatus !== 'endorsed') {
                return response()->json(['message' => 'Only school-endorsed applications can be reviewed by the company.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        if ($appRole === 'student' && $appRole !== 'admin') {
            return response()->json(['message' => 'Students cannot update application decisions.'], Response::HTTP_FORBIDDEN);
        }

        $previousStatus = $currentStatus;
        $application->update(['status' => $nextStatus]);

        $freshApplication = $application->fresh([
            'student:id,user_id,school_id,school_subscription_code,school_name',
            'internship:id,company_id,title',
            'internship.company:id,user_id,company_name',
            'company:id,user_id,company_name',
        ]);

        if ($previousStatus !== $nextStatus) {
            $this->notifyApplicationStatusChange($freshApplication, $nextStatus);
        }

        return response()->json(['data' => $this->serialize($freshApplication)]);
    }

    private function notifyApplicationStatusChange(Application $application, string $nextStatus): void
    {
        $title = $application->internship_title ?: 'internship application';

        if ($nextStatus === 'endorsed') {
            $companyUserId = $application->company?->user_id ?? $application->internship?->company?->user_id;
            if ($companyUserId) {
                $this->createInAppNotification(
                    (int) $companyUserId,
                    'Application endorsed',
                    trim(($application->student_name ?: 'A student').' was endorsed for '.$title.'.'),
                    [
                        'type' => 'endorsement',
                        'applicationId' => (string) $application->id,
                        'redirectTo' => '/dashboard/applicants',
                    ]
                );
            }

            return;
        }

        $studentUserId = $application->student?->user_id;
        if (! $studentUserId) {
            return;
        }

        if ($nextStatus === 'school_rejected') {
            $this->createInAppNotification(
                (int) $studentUserId,
                'Application not endorsed',
                'Your school did not endorse your application for '.$title.'.',
                [
                    'type' => 'application',
                    'applicationId' => (string) $application->id,
                    'redirectTo' => '/intern/applications',
                ]
            );

            return;
        }

        if ($nextStatus === 'accepted') {
            $this->createInAppNotification(
                (int) $studentUserId,
                'Application accepted',
                'Your application for '.$title.' was accepted.',
                [
                    'type' => 'application',
                    'applicationId' => (string) $application->id,
                    'redirectTo' => '/intern/applications',
                ]
            );

            return;
        }

        if ($nextStatus === 'rejected') {
            $this->createInAppNotification(
                (int) $studentUserId,
                'Application rejected',
                'Your application for '.$title.' was rejected by the company.',
                [
                    'type' => 'application',
                    'applicationId' => (string) $application->id,
                    'redirectTo' => '/intern/applications',
                ]
            );
        }
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
    private function serialize(Application $a): array
    {
        $companyUserId = '';
        if ($a->internship?->company?->user_id) {
            $companyUserId = (string) $a->internship->company->user_id;
        }

        return [
            'id' => (string) $a->id,
            'internshipId' => (string) $a->internship_id,
            'studentId' => $a->student ? (string) $a->student->user_id : '',
            'companyId' => $companyUserId,
            'internshipTitle' => $a->internship_title,
            'studentName' => $a->student_name,
            'studentEmail' => $a->student_email,
            'studentCourse' => $a->student_course,
            'schoolName' => $a->student?->school_name,
            'companyName' => $a->internship?->company?->company_name,
            'status' => $a->status,
            'resume' => $a->resume_url,
            'documents' => $a->documents,
            'documentsPending' => $a->documents_pending,
            'createdAt' => $a->created_at?->toIso8601String(),
            'updatedAt' => $a->updated_at?->toIso8601String(),
        ];
    }
}
