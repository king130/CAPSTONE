<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Document;
use App\Models\Internship;
use App\Models\Notification;
use App\Models\SchoolCourseRequirement;
use App\Models\Student;
use App\Services\ApplicationTracker;
use App\Services\CourseYearLevelGate;
use App\Services\HoursAgreementService;
use App\Services\InternshipEligibilityService;
use App\Services\PermissionGate;
use App\Services\RequiredOjtHoursAccessor;
use App\Support\InternshipDocumentRequirements;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class ApplicationController extends Controller
{
    public function __construct(
        private readonly PermissionGate $permissions,
        private readonly InternshipEligibilityService $internshipEligibility,
        private readonly ApplicationTracker $tracker,
        private readonly CourseYearLevelGate $courseYearLevels,
        private readonly HoursAgreementService $hoursAgreements,
        private readonly RequiredOjtHoursAccessor $hoursAccessor,
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
                'tracker_stage',
                'resume_url',
                'documents',
                'documents_pending',
                'internship_title',
                'student_name',
                'student_email',
                'student_course',
                'required_ojt_hours',
                'created_at',
                'updated_at',
            ])
            ->with([
                'student:id,user_id,school_id,school_subscription_code,school_name',
                'internship:id,company_id,school_id,host_type,host_name,title,host_required_hours',
                'internship.company:id,user_id,company_name',
                'internship.school:id,institution_name',
                // List view only needs the agreement row; event history is loaded on the detail endpoint.
                'hoursAgreement',
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
            'endorsement_url' => ['nullable', 'string', 'max:2048'],
            'documents' => ['nullable', 'array'],
            'cover_letter' => ['nullable', 'string'],
        ]);

        $internship = Internship::query()->with('company')->findOrFail($data['internship_id']);

        $student = $user->student;

        // Course hours / year eligibility before partnership checks so setup messages are clear.
        if ($blocked = $this->courseYearLevels->rejectIfCannotApply($student)) {
            return $blocked;
        }

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

        $alreadyApplied = Application::query()
            ->where('student_id', $student->id)
            ->where('internship_id', $internship->id)
            ->exists();

        if ($alreadyApplied) {
            $message = 'You have already applied to this internship.';

            return response()->json([
                'message' => $message,
                'code' => 'duplicate_application',
                'errors' => [
                    'internship_id' => [$message],
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Resume is optional profile information for hosts — never a posting requirement.
        $resume = $this->resolveOptionalResume($user, $data['resume_url'] ?? null);

        $endorsement = $this->resolveApplicationEndorsement(
            $user,
            $data['endorsement_url'] ?? null,
            $data['documents'] ?? null
        );
        if ($endorsement instanceof \Illuminate\Http\JsonResponse) {
            return $endorsement;
        }

        $documents = is_array($data['documents'] ?? null) ? array_values($data['documents']) : [];
        if (! in_array($endorsement, $documents, true)) {
            array_unshift($documents, $endorsement);
        }

        $companyId = $internship->company_id;
        $requiredOjtHours = $this->resolveRequiredOjtHoursForStudent($student);

        $application = Application::query()->create([
            'internship_id' => $internship->id,
            'student_id' => $student->id,
            'company_id' => $companyId,
            'status' => 'submitted',
            'tracker_stage' => ApplicationTracker::STAGE_APPLIED,
            'cover_letter' => $data['cover_letter'] ?? null,
            'resume_url' => $resume,
            'documents' => $documents,
            'documents_pending' => false,
            'internship_title' => $internship->title,
            'student_name' => $user->name,
            'student_email' => $user->email,
            'student_course' => $student->course,
            'required_ojt_hours' => $requiredOjtHours,
        ]);

        $application->load([
            'student:id,user_id,school_id,school_subscription_code,school_name',
            'student.school:id,user_id,institution_name',
            'internship:id,company_id,school_id,host_type,host_name,title,host_required_hours',
            'internship.company:id,user_id,company_name',
            'internship.school:id,institution_name',
        ]);

        // Phase D: create pending hours agreement when host hours differ from school snapshot.
        $this->hoursAgreements->createOnApplyIfNeeded($application, $internship);
        $application->load('hoursAgreement.events.changedBy');

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
            && $internship->company_id
            && (int) $internship->company_id === (int) $company->id;

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

        if ($appRole === 'student') {
            return response()->json(['message' => 'Students cannot update application decisions.'], Response::HTTP_FORBIDDEN);
        }

        $data = $request->validate([
            'status' => ['required', 'string', 'max:64'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $nextStatus = strtolower(trim((string) $data['status']));
        $this->tracker->ensureStageColumn($application);
        $currentStage = $this->tracker->currentStage($application);
        $isSchoolHosted = $this->isSchoolHostedInternship($internship);
        $hostsSchoolInternship = $isSchoolHosted
            && $school
            && $internship
            && (int) $internship->school_id === (int) $school->id;

        $stageMap = [
            'endorsed' => ApplicationTracker::STAGE_ENDORSED,
            'school_rejected' => ApplicationTracker::STAGE_SCHOOL_REJECTED,
            'accepted' => ApplicationTracker::STAGE_ACCEPTED,
            'rejected' => ApplicationTracker::STAGE_REJECTED,
        ];

        if (! isset($stageMap[$nextStatus])) {
            return response()->json([
                'message' => 'Use the interview tracker for stage changes. Allowed status values: endorsed, school_rejected, accepted, rejected.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $toStage = $stageMap[$nextStatus];

        if ($appRole === 'school') {
            if (! $this->permissions->userCan($user, 'org.approve_applications')) {
                return response()->json(['message' => 'Missing permission to approve applications.'], Response::HTTP_FORBIDDEN);
            }

            if (in_array($toStage, [ApplicationTracker::STAGE_ENDORSED, ApplicationTracker::STAGE_SCHOOL_REJECTED], true)) {
                if ($currentStage !== ApplicationTracker::STAGE_APPLIED) {
                    return response()->json(['message' => 'Only newly submitted applications can be endorsed by the school.'], Response::HTTP_UNPROCESSABLE_ENTITY);
                }
            } elseif (in_array($toStage, [ApplicationTracker::STAGE_ACCEPTED, ApplicationTracker::STAGE_REJECTED], true)) {
                if (! $hostsSchoolInternship) {
                    return response()->json([
                        'message' => 'Schools can only accept or reject assessed school-hosted applications that they host.',
                    ], Response::HTTP_UNPROCESSABLE_ENTITY);
                }
                if ($currentStage !== ApplicationTracker::STAGE_ASSESSED) {
                    return response()->json([
                        'message' => 'Accept or reject is only allowed after assessment. Use the interview tracker.',
                    ], Response::HTTP_UNPROCESSABLE_ENTITY);
                }
            } else {
                return response()->json([
                    'message' => 'Schools can endorse or school-reject new applications, or accept/reject assessed school-hosted applications they host.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        if ($appRole === 'company') {
            if (! $this->permissions->userCan($user, 'org.approve_applications')) {
                return response()->json(['message' => 'Missing permission to approve applications.'], Response::HTTP_FORBIDDEN);
            }
            if ($isSchoolHosted || ! $canCompany) {
                return response()->json([
                    'message' => 'Companies can only accept or reject assessed applications for their own company-hosted internships.',
                ], Response::HTTP_FORBIDDEN);
            }
            if (! in_array($toStage, [ApplicationTracker::STAGE_ACCEPTED, ApplicationTracker::STAGE_REJECTED], true)) {
                return response()->json(['message' => 'Companies can only accept or reject after assessment via the interview tracker.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            if ($currentStage !== ApplicationTracker::STAGE_ASSESSED) {
                return response()->json([
                    'message' => 'Accept or reject is only allowed after assessment. Use the interview tracker.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        if ($toStage === ApplicationTracker::STAGE_REJECTED && trim((string) ($data['reason'] ?? '')) === '') {
            throw ValidationException::withMessages([
                'reason' => ['A rejection reason is required.'],
            ]);
        }

        $previousStatus = strtolower(trim((string) $application->status));

        try {
            $this->tracker->transition(
                $application,
                $toStage,
                $user,
                $data['reason'] ?? null,
                false,
            );
        } catch (ValidationException $e) {
            throw $e;
        }

        $freshApplication = $application->fresh([
            'student:id,user_id,school_id,school_subscription_code,school_name',
            'internship:id,company_id,school_id,host_type,host_name,title',
            'internship.company:id,user_id,company_name',
            'internship.school:id,institution_name',
            'company:id,user_id,company_name',
        ]);

        $legacyNext = $this->tracker->legacyStatusForStage($toStage);
        if ($previousStatus !== $legacyNext) {
            $this->notifyApplicationStatusChange($freshApplication, $legacyNext);
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
            $rejectedBy = $this->isSchoolHostedInternship($application->internship)
                ? 'by your school'
                : 'by the company';
            $this->createInAppNotification(
                (int) $studentUserId,
                'Application rejected',
                'Your application for '.$title.' was rejected '.$rejectedBy.'.',
                [
                    'type' => 'application',
                    'applicationId' => (string) $application->id,
                    'redirectTo' => '/intern/applications',
                ]
            );
        }
    }

    private function isSchoolHostedInternship(?Internship $internship): bool
    {
        if (! $internship) {
            return false;
        }

        if (strtolower(trim((string) $internship->host_type)) === 'school') {
            return true;
        }

        return $internship->company_id === null && $internship->school_id !== null;
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
     * Optional resume link for host visibility (profile field). Never blocks apply.
     */
    private function resolveOptionalResume($user, ?string $resumeUrl): ?string
    {
        $requested = trim((string) $resumeUrl);
        if ($requested === '') {
            $owned = Document::query()
                ->where('owner_id', $user->id)
                ->where('owner_type', 'student')
                ->whereNotNull('file_url')
                ->orderByDesc('id')
                ->get()
                ->first(function (Document $document) {
                    return in_array(strtolower((string) $document->category), ['application-resume', 'internship-application'], true)
                        && trim((string) $document->file_url) !== '';
                });

            return $owned ? trim((string) $owned->file_url) : null;
        }

        $ownedUrls = $this->ownedDocumentUrls($user, ['application-resume', 'internship-application']);
        if ($ownedUrls->contains($requested)) {
            return $requested;
        }

        // Allow a free-form profile link without ownership when provided explicitly.
        return $requested;
    }

    /**
     * Endorsement letter is the only required application document.
     *
     * @param  mixed  $documentsPayload
     * @return string|\Illuminate\Http\JsonResponse
     */
    private function resolveApplicationEndorsement($user, ?string $endorsementUrl, mixed $documentsPayload)
    {
        $requested = trim((string) $endorsementUrl);
        $categories = InternshipDocumentRequirements::endorsementDocumentCategories();
        $ownedUrls = $this->ownedDocumentUrls($user, $categories);

        if ($requested !== '' && $ownedUrls->contains($requested)) {
            return $requested;
        }

        if (is_array($documentsPayload)) {
            foreach ($documentsPayload as $item) {
                $url = trim((string) $item);
                if ($url !== '' && $ownedUrls->contains($url)) {
                    return $url;
                }
            }
        }

        if ($ownedUrls->isNotEmpty()) {
            return (string) $ownedUrls->first();
        }

        $message = 'An endorsement letter is required to apply. Upload or reuse your endorsement document first.';

        return response()->json([
            'message' => $message,
            'code' => 'endorsement_required',
            'errors' => [
                'endorsement_url' => [$message],
            ],
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * @param  list<string>  $categories
     * @return \Illuminate\Support\Collection<int, string>
     */
    private function ownedDocumentUrls($user, array $categories)
    {
        $normalized = array_map(static fn (string $c) => strtolower($c), $categories);

        return Document::query()
            ->where('owner_id', $user->id)
            ->where('owner_type', 'student')
            ->orderByDesc('id')
            ->get()
            ->filter(function (Document $document) use ($normalized) {
                if (! in_array(strtolower((string) $document->category), $normalized, true)) {
                    return false;
                }
                $url = trim((string) ($document->file_url ?: ''));
                $path = trim((string) ($document->file_path ?: ''));

                return $url !== '' || $path !== '';
            })
            ->map(function (Document $document) {
                $url = trim((string) ($document->file_url ?: ''));
                if ($url !== '') {
                    return $url;
                }

                return '/api/documents/'.$document->id.'/file';
            })
            ->unique()
            ->values();
    }

    /**
     * Required OJT hours come from the student's school + course (never from the host org).
     */
    private function resolveRequiredOjtHoursForStudent(Student $student): ?int
    {
        $course = trim((string) ($student->course ?? ''));
        if ($student->school_id && $course !== '') {
            $fromCourse = SchoolCourseRequirement::query()
                ->where('school_id', $student->school_id)
                ->where('course_name_key', SchoolCourseRequirement::courseNameKey($course))
                ->value('required_hours');
            if (is_numeric($fromCourse) && (int) $fromCourse >= 1) {
                return (int) $fromCourse;
            }
        }

        $organization = $student->school?->organization
            ?? ($student->organization_id
                ? \App\Models\Organization::query()->find($student->organization_id)
                : null);
        if ($organization && $organization->type === 'school' && is_array($organization->settings)) {
            foreach (['required_ojt_hours', 'requiredHours'] as $key) {
                if (! array_key_exists($key, $organization->settings) || ! is_numeric($organization->settings[$key])) {
                    continue;
                }
                $hours = (int) $organization->settings[$key];
                if ($hours >= 1) {
                    return $hours;
                }
            }
        }

        return null;
    }

    private function serialize(Application $a): array
    {
        $companyUserId = '';
        if ($a->internship?->company?->user_id) {
            $companyUserId = (string) $a->internship->company->user_id;
        }

        $hostType = strtolower(trim((string) ($a->internship?->host_type ?: '')));
        if ($hostType === '') {
            $hostType = $a->internship?->company_id ? 'company' : 'school';
        }

        $a->loadMissing(['hoursAgreement', 'internship']);
        $hoursPayload = $this->hoursAgreements->serialize($a->hoursAgreement, $a, $a->internship, false);

        return [
            'id' => (string) $a->id,
            'internshipId' => (string) $a->internship_id,
            'studentId' => $a->student ? (string) $a->student->user_id : '',
            'companyId' => $companyUserId,
            'internshipTitle' => $a->internship_title,
            'studentName' => $a->student_name,
            'studentEmail' => $a->student_email,
            'studentCourse' => $a->student_course,
            'schoolName' => $a->student?->school_name ?: $a->internship?->school?->institution_name,
            'companyName' => $a->internship?->company?->company_name,
            'hostType' => $hostType,
            'hostName' => $a->internship?->host_name
                ?: ($hostType === 'school'
                    ? ($a->internship?->school?->institution_name ?: $a->student?->school_name)
                    : $a->internship?->company?->company_name),
            'status' => $a->status,
            'trackerStage' => $a->tracker_stage ?: $this->tracker->inferStage($a),
            'resume' => $a->resume_url,
            'documents' => $a->documents,
            'documentsPending' => $a->documents_pending,
            'requiredOjtHours' => $a->required_ojt_hours !== null ? (int) $a->required_ojt_hours : null,
            'effectiveRequiredHours' => $this->hoursAccessor->forApplication($a),
            'hoursMismatch' => $hoursPayload['hoursMismatch'],
            'schoolRequiredHours' => $hoursPayload['schoolRequiredHours'],
            'hostRequiredHours' => $hoursPayload['hostRequiredHours'],
            'hoursAgreement' => $hoursPayload['agreement'],
            'createdAt' => $a->created_at?->toIso8601String(),
            'updatedAt' => $a->updated_at?->toIso8601String(),
        ];
    }
}
