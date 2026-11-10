<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\OjtDtrSubmission;
use App\Models\School;
use App\Models\Student;
use App\Services\DtrStatus;
use App\Services\PermissionGate;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class OjtDtrController extends Controller
{
    public function __construct(private readonly PermissionGate $permissions)
    {
    }

    public function placements(Request $request)
    {
        $user = $request->user();
        $role = $user->effectiveAppRole();

        if ($role === 'student' && $user->student) {
            $applications = Application::query()
                ->with(['company:id,company_name'])
                ->where('student_id', $user->student->id)
                ->where('status', 'accepted')
                ->orderByDesc('updated_at')
                ->get()
                ->map(fn (Application $application) => [
                    'applicationId' => (int) $application->id,
                    'internId' => (int) $application->student_id,
                    'internName' => $user->name ?? 'Intern',
                    'internshipTitle' => $application->internship_title ?? 'Internship',
                    'companyName' => $application->company?->company_name ?? '',
                ])
                ->values();

            return response()->json(['data' => $applications]);
        }

        $company = $user->organizationCompany() ?? $user->company;
        if ($role !== 'company' || ! $company || ! $this->canUpload($user)) {
            return response()->json(['message' => 'Missing permission to submit DTR files.'], Response::HTTP_FORBIDDEN);
        }

        $applications = Application::query()
            ->with(['student.user:id,name,email'])
            ->where('company_id', $company->id)
            ->where('status', 'accepted')
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (Application $application) => [
                'applicationId' => (int) $application->id,
                'internId' => (int) $application->student_id,
                'internName' => $application->student?->user?->name
                    ?? $application->student_name
                    ?? 'Intern',
                'internshipTitle' => $application->internship_title ?? 'Internship',
                'companyName' => $company->company_name ?? '',
            ])
            ->values();

        return response()->json(['data' => $applications]);
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $query = $this->visibleQuery($user);
        if ($query instanceof \Illuminate\Http\JsonResponse) {
            return $query;
        }

        if ($request->filled('status')) {
            $status = (string) $request->string('status');
            $allowed = [
                OjtDtrSubmission::STATUS_SUBMITTED,
                OjtDtrSubmission::STATUS_APPROVED,
                OjtDtrSubmission::STATUS_REJECTED,
                OjtDtrSubmission::STATUS_REVISION_REQUIRED,
                'pending',
            ];
            if (! in_array($status, $allowed, true)) {
                return response()->json(['message' => 'Unknown DTR status.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            if ($status === 'pending' || $status === OjtDtrSubmission::STATUS_SUBMITTED) {
                $query->whereIn('status', [
                    OjtDtrSubmission::STATUS_SUBMITTED,
                    OjtDtrSubmission::STATUS_PENDING_COMPANY,
                    OjtDtrSubmission::STATUS_PENDING_COORDINATOR,
                    'pending',
                ]);
            } elseif ($status === OjtDtrSubmission::STATUS_REJECTED) {
                $query->whereIn('status', [
                    OjtDtrSubmission::STATUS_REJECTED,
                    OjtDtrSubmission::STATUS_REVISION_REQUIRED,
                ]);
            } else {
                $query->where('status', $status);
            }
        }

        $rows = $query->orderByDesc('created_at')->get();

        return response()->json([
            'data' => $rows->map(fn (OjtDtrSubmission $row) => $this->serialize($row))->values(),
        ]);
    }

    public function show(Request $request, OjtDtrSubmission $ojtDtrSubmission)
    {
        if (! $this->canView($request->user(), $ojtDtrSubmission)) {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        return response()->json(['data' => $this->serialize($ojtDtrSubmission)]);
    }

    public function destroy(Request $request, OjtDtrSubmission $ojtDtrSubmission)
    {
        if (! $this->canDelete($request->user(), $ojtDtrSubmission)) {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        if (! $ojtDtrSubmission->isPending()) {
            return response()->json([
                'message' => 'Only a DTR that is still awaiting review can be deleted. Approved and rejected records stay on file.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($ojtDtrSubmission->file_path) {
            Storage::disk('local')->delete($ojtDtrSubmission->file_path);
        }

        $ojtDtrSubmission->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $role = $user->effectiveAppRole();
        $data = $this->validatePayload($request, true);

        if ($role === 'student' && $user->student) {
            $application = $this->acceptedApplicationForStudent((int) $user->student->id, (int) $data['application_id']);
            if ($application instanceof \Illuminate\Http\JsonResponse) {
                return $application;
            }

            $companyId = (int) $application->company_id;
            $entry = DtrStatus::forEntry('student', (int) $user->id);
        } else {
            $company = $user->organizationCompany() ?? $user->company;
            if ($role !== 'company' || ! $company || ! $this->canUpload($user)) {
                return response()->json(['message' => 'Missing permission to submit DTR files.'], Response::HTTP_FORBIDDEN);
            }

            $application = $this->acceptedApplicationForCompany($company->id, (int) $data['application_id']);
            if ($application instanceof \Illuminate\Http\JsonResponse) {
                return $application;
            }

            $companyId = (int) $company->id;
            $entry = DtrStatus::forEntry('company', (int) $user->id);
        }

        $file = $request->file('file');
        $storedPath = $file->store('ojt-dtrs/'.$companyId, 'local');

        try {
            $submission = DB::transaction(function () use (
                $user,
                $companyId,
                $application,
                $data,
                $file,
                $storedPath,
                $entry
            ) {
                $open = OjtDtrSubmission::query()
                    ->where('application_id', $application->id)
                    ->whereIn('status', OjtDtrSubmission::openStatuses())
                    ->lockForUpdate()
                    ->exists();
                if ($open) {
                    throw ValidationException::withMessages([
                        'application_id' => ['This intern already has a DTR waiting for review.'],
                    ]);
                }

                $overlap = OjtDtrSubmission::query()
                    ->where('application_id', $application->id)
                    ->where('student_id', $application->student_id)
                    ->where('status', '!=', OjtDtrSubmission::STATUS_REJECTED)
                    ->whereDate('period_start', '<=', $data['period_end'])
                    ->whereDate('period_end', '>=', $data['period_start'])
                    ->lockForUpdate()
                    ->exists();
                if ($overlap) {
                    throw ValidationException::withMessages([
                        'period_start' => ['This DTR period overlaps an existing DTR for the same intern. Choose dates that do not overlap.'],
                    ]);
                }

                $submission = OjtDtrSubmission::query()->create(array_merge($entry, [
                    'application_id' => $application->id,
                    'student_id' => $application->student_id,
                    'company_id' => $companyId,
                    'submitted_by_user_id' => $user->id,
                    'period_start' => $data['period_start'],
                    'period_end' => $data['period_end'],
                    'submitted_hours' => $data['submitted_hours'],
                    'remarks' => $data['remarks'] ?? null,
                    'file_path' => $storedPath,
                    'file_name' => $this->safeFileName($file->getClientOriginalName()),
                    'file_mime' => $file->getClientMimeType(),
                    'file_size' => $file->getSize(),
                ]));
                $this->lockOjtStart($submission);

                return $submission;
            });
        } catch (ValidationException $exception) {
            Storage::disk('local')->delete($storedPath);
            throw $exception;
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($storedPath);
            throw $exception;
        }

        return response()->json(['data' => $this->serialize($submission)], Response::HTTP_CREATED);
    }

    public function resubmit(Request $request, OjtDtrSubmission $ojtDtrSubmission)
    {
        $user = $request->user();
        $role = $user->effectiveAppRole();
        $data = $this->validatePayload($request, true);

        $allowedStatuses = [
            OjtDtrSubmission::STATUS_REJECTED,
            OjtDtrSubmission::STATUS_REVISION_REQUIRED,
        ];
        if (! in_array($ojtDtrSubmission->status, $allowedStatuses, true)) {
            return response()->json(['message' => 'Only a rejected or revision-required DTR can be resubmitted.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($role === 'student' && $user->student) {
            if ((int) $ojtDtrSubmission->student_id !== (int) $user->student->id) {
                return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
            }
            $companyId = (int) $ojtDtrSubmission->company_id;
            $entry = DtrStatus::forEntry('student', (int) $user->id);
        } else {
            $company = $user->organizationCompany() ?? $user->company;
            if ($role !== 'company' || ! $company || ! $this->canUpload($user)) {
                return response()->json(['message' => 'Missing permission to submit DTR files.'], Response::HTTP_FORBIDDEN);
            }
            if ((int) $ojtDtrSubmission->company_id !== (int) $company->id) {
                return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
            }
            $companyId = (int) $company->id;
            $entry = DtrStatus::forEntry('company', (int) $user->id);
        }

        if ((int) $data['application_id'] !== (int) $ojtDtrSubmission->application_id) {
            return response()->json(['message' => 'Resubmission must stay on the same intern.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $file = $request->file('file');
        $storedPath = $file->store('ojt-dtrs/'.$companyId, 'local');

        try {
            $submission = DB::transaction(function () use (
                $user,
                $companyId,
                $ojtDtrSubmission,
                $data,
                $file,
                $storedPath,
                $entry
            ) {
                $locked = OjtDtrSubmission::query()->whereKey($ojtDtrSubmission->id)->lockForUpdate()->first();
                if (! $locked || ! in_array($locked->status, [
                    OjtDtrSubmission::STATUS_REJECTED,
                    OjtDtrSubmission::STATUS_REVISION_REQUIRED,
                ], true)) {
                    throw ValidationException::withMessages([
                        'file' => ['Only a rejected or revision-required DTR can be resubmitted.'],
                    ]);
                }

                // Exclude the row being replaced; revision_required/rejected are open for
                // new uploads but must not block their own resubmit path.
                $open = OjtDtrSubmission::query()
                    ->where('application_id', $locked->application_id)
                    ->whereIn('status', OjtDtrSubmission::openStatuses())
                    ->whereKeyNot($locked->id)
                    ->lockForUpdate()
                    ->exists();
                if ($open) {
                    throw ValidationException::withMessages([
                        'application_id' => ['This intern already has a DTR waiting for review.'],
                    ]);
                }

                $submission = OjtDtrSubmission::query()->create(array_merge($entry, [
                    'application_id' => $locked->application_id,
                    'student_id' => $locked->student_id,
                    'company_id' => $companyId,
                    'submitted_by_user_id' => $user->id,
                    'previous_submission_id' => $locked->id,
                    'period_start' => $data['period_start'],
                    'period_end' => $data['period_end'],
                    'submitted_hours' => $data['submitted_hours'],
                    'remarks' => $data['remarks'] ?? null,
                    'file_path' => $storedPath,
                    'file_name' => $this->safeFileName($file->getClientOriginalName()),
                    'file_mime' => $file->getClientMimeType(),
                    'file_size' => $file->getSize(),
                ]));
                $this->lockOjtStart($submission);

                return $submission;
            });
        } catch (ValidationException $exception) {
            Storage::disk('local')->delete($storedPath);
            throw $exception;
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($storedPath);
            throw $exception;
        }

        return response()->json(['data' => $this->serialize($submission)], Response::HTTP_CREATED);
    }

    public function approve(Request $request, OjtDtrSubmission $ojtDtrSubmission)
    {
        $user = $request->user();
        $role = $user->effectiveAppRole();

        if ($role === 'company') {
            return $this->companyApprove($user, $ojtDtrSubmission);
        }

        if (! $this->canCoordinatorReview($user, $ojtDtrSubmission)) {
            return response()->json(['message' => 'Missing permission to approve DTR hours.'], Response::HTTP_FORBIDDEN);
        }

        $allowHost = $role === 'admin';
        $updated = DB::transaction(function () use ($user, $ojtDtrSubmission, $role, $allowHost) {
            $locked = OjtDtrSubmission::query()->whereKey($ojtDtrSubmission->id)->lockForUpdate()->first();
            $awaiting = $locked && ($locked->isAwaitingSchool() || ($allowHost && $locked->isAwaitingHost()));
            if (! $awaiting) {
                return null;
            }

            $locked->update(DtrStatus::forApproval($role, (int) $user->id));
            $this->lockOjtStart($locked);

            return $locked;
        });

        if (! $updated) {
            return response()->json([
                'message' => 'Only a DTR pending coordinator approval can be approved.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json(['data' => $this->serialize($updated->fresh($this->relations()))]);
    }

    public function reject(Request $request, OjtDtrSubmission $ojtDtrSubmission)
    {
        $user = $request->user();
        $role = $user->effectiveAppRole();
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
            'revision_required' => ['sometimes', 'boolean'],
        ]);
        $reason = trim($data['reason']);

        if ($role === 'company') {
            return $this->companyReject($user, $ojtDtrSubmission, $reason);
        }

        if (! $this->canCoordinatorReview($user, $ojtDtrSubmission)) {
            return response()->json(['message' => 'Missing permission to reject DTR hours.'], Response::HTTP_FORBIDDEN);
        }

        $allowHost = $role === 'admin';
        $updated = DB::transaction(function () use ($user, $ojtDtrSubmission, $role, $reason, $allowHost) {
            $locked = OjtDtrSubmission::query()->whereKey($ojtDtrSubmission->id)->lockForUpdate()->first();
            $awaiting = $locked && ($locked->isAwaitingSchool() || ($allowHost && $locked->isAwaitingHost()));
            if (! $awaiting) {
                return null;
            }

            $locked->update(DtrStatus::forRejection($role, (int) $user->id, $reason));

            return $locked;
        });

        if (! $updated) {
            return response()->json([
                'message' => 'Only a DTR pending coordinator approval can be rejected.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json(['data' => $this->serialize($updated->fresh($this->relations()))]);
    }

    public function download(Request $request, OjtDtrSubmission $ojtDtrSubmission)
    {
        if (! $this->canView($request->user(), $ojtDtrSubmission)) {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        if ($ojtDtrSubmission->file_path === '' || ! Storage::disk('local')->exists($ojtDtrSubmission->file_path)) {
            return response()->json(['message' => 'DTR file is no longer available.'], Response::HTTP_NOT_FOUND);
        }

        return Storage::disk('local')->download($ojtDtrSubmission->file_path, $ojtDtrSubmission->file_name);
    }

    private function companyApprove($user, OjtDtrSubmission $ojtDtrSubmission)
    {
        $company = $user->organizationCompany() ?? $user->company;
        if (! $company || ! $this->canCompanyReview($user)) {
            return response()->json(['message' => 'Missing permission to approve DTR hours.'], Response::HTTP_FORBIDDEN);
        }
        if ((int) $ojtDtrSubmission->company_id !== (int) $company->id) {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $updated = DB::transaction(function () use ($user, $ojtDtrSubmission) {
            $locked = OjtDtrSubmission::query()->whereKey($ojtDtrSubmission->id)->lockForUpdate()->first();
            if (! $locked || ! $locked->isAwaitingHost()) {
                return null;
            }

            $locked->update(DtrStatus::forApproval('company', (int) $user->id));
            $this->lockOjtStart($locked);

            return $locked;
        });

        if (! $updated) {
            return response()->json([
                'message' => 'Only a DTR pending host approval can be approved by the host organization.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json(['data' => $this->serialize($updated->fresh($this->relations()))]);
    }

    private function companyReject($user, OjtDtrSubmission $ojtDtrSubmission, string $reason)
    {
        $company = $user->organizationCompany() ?? $user->company;
        if (! $company || ! $this->canCompanyReview($user)) {
            return response()->json(['message' => 'Missing permission to reject DTR hours.'], Response::HTTP_FORBIDDEN);
        }
        if ((int) $ojtDtrSubmission->company_id !== (int) $company->id) {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $updated = DB::transaction(function () use ($user, $ojtDtrSubmission, $reason) {
            $locked = OjtDtrSubmission::query()->whereKey($ojtDtrSubmission->id)->lockForUpdate()->first();
            if (! $locked || ! $locked->isAwaitingHost()) {
                return null;
            }

            $locked->update(DtrStatus::forRejection('company', (int) $user->id, $reason));

            return $locked;
        });

        if (! $updated) {
            return response()->json([
                'message' => 'Only a DTR pending host approval can be rejected by the host organization.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json(['data' => $this->serialize($updated->fresh($this->relations()))]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, bool $requireFile): array
    {
        return $request->validate([
            'application_id' => ['required', 'integer', 'exists:applications,id'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'submitted_hours' => ['required', 'numeric', 'min:0.25', 'max:500'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'file' => array_filter([
                $requireFile ? 'required' : 'nullable',
                'file',
                'max:10240',
                'mimes:pdf,jpg,jpeg,png,doc,docx',
            ]),
        ]);
    }

    /**
     * @return Application|\Illuminate\Http\JsonResponse
     */
    private function acceptedApplicationForCompany(int $companyId, int $applicationId)
    {
        $application = Application::query()
            ->whereKey($applicationId)
            ->where('company_id', $companyId)
            ->first();

        if (! $application) {
            return response()->json(['message' => 'That intern is not placed with your organization.'], Response::HTTP_FORBIDDEN);
        }

        if (strtolower(trim((string) $application->status)) !== 'accepted') {
            return response()->json([
                'message' => 'DTR files can only be submitted for an accepted intern.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $application;
    }

    /**
     * @return Application|\Illuminate\Http\JsonResponse
     */
    private function acceptedApplicationForStudent(int $studentId, int $applicationId)
    {
        $application = Application::query()
            ->whereKey($applicationId)
            ->where('student_id', $studentId)
            ->first();

        if (! $application) {
            return response()->json(['message' => 'That placement does not belong to you.'], Response::HTTP_FORBIDDEN);
        }

        if (strtolower(trim((string) $application->status)) !== 'accepted') {
            return response()->json([
                'message' => 'DTR files can only be submitted for an accepted placement.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $application;
    }

    private function canUpload($user): bool
    {
        return $this->permissions->userCanAny($user, [
            'org.manage_internships',
            'manage_internships',
            'org.manage_applications',
            'org.approve_ojt_logs',
            'review_applications',
        ]);
    }

    private function canCompanyReview($user): bool
    {
        return $this->canUpload($user);
    }

    private function canCoordinatorReview($user, OjtDtrSubmission $submission): bool
    {
        if ($user->effectiveAppRole() === 'company') {
            return false;
        }

        if ($user->effectiveAppRole() !== 'admin' && ! $this->permissions->userCan($user, 'org.approve_ojt_logs')) {
            return false;
        }

        if ($user->effectiveAppRole() === 'admin') {
            return true;
        }

        return $this->studentBelongsToSchool($submission->student, $user->organizationSchool() ?? $user->school);
    }

    private function canDelete($user, OjtDtrSubmission $submission): bool
    {
        if (! $this->canView($user, $submission)) {
            return false;
        }

        $role = $user->effectiveAppRole();
        if ($role === 'admin' || $role === 'student') {
            return true;
        }

        if ($role === 'company') {
            return $this->canUpload($user);
        }

        if ($role === 'school') {
            return $this->permissions->userCan($user, 'org.approve_ojt_logs');
        }

        return false;
    }

    private function canView($user, OjtDtrSubmission $submission): bool
    {
        $role = $user->effectiveAppRole();
        if ($role === 'admin') {
            return true;
        }

        if ($role === 'student' && $user->student) {
            return (int) $submission->student_id === (int) $user->student->id;
        }

        $company = $user->organizationCompany() ?? $user->company;
        if ($role === 'company' && $company && $this->canUpload($user)) {
            return (int) $submission->company_id === (int) $company->id;
        }

        $school = $user->organizationSchool() ?? $user->school;
        if ($role === 'school' && $school && $this->permissions->userCanAny($user, ['org.approve_ojt_logs', 'org.view_ojt_progress'])) {
            return $this->studentBelongsToSchool($submission->student, $school);
        }

        return false;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<OjtDtrSubmission>|\Illuminate\Http\JsonResponse
     */
    private function visibleQuery($user)
    {
        $role = $user->effectiveAppRole();
        $query = OjtDtrSubmission::query()->with($this->relations());

        if ($role === 'admin') {
            return $query;
        }

        if ($role === 'student' && $user->student) {
            return $query->where('student_id', $user->student->id);
        }

        $company = $user->organizationCompany() ?? $user->company;
        if ($role === 'company' && $company && $this->canUpload($user)) {
            return $query->where('company_id', $company->id);
        }

        $school = $user->organizationSchool() ?? $user->school;
        if ($role === 'school' && $school && $this->permissions->userCanAny($user, ['org.approve_ojt_logs', 'org.view_ojt_progress'])) {
            return $query->whereHas('student', function ($studentQuery) use ($school) {
                $studentQuery->where('school_id', $school->id);
                if ($school->subscription_code) {
                    $studentQuery->orWhere('school_subscription_code', $school->subscription_code);
                }
            });
        }

        return response()->json(['message' => 'Missing permission to view DTR submissions.'], Response::HTTP_FORBIDDEN);
    }

    private function studentBelongsToSchool(?Student $student, ?School $school): bool
    {
        if (! $student || ! $school) {
            return false;
        }

        if ((int) $student->school_id === (int) $school->id) {
            return true;
        }

        return $school->subscription_code
            && $student->school_subscription_code === $school->subscription_code;
    }

    /**
     * @return array<int, string>
     */
    private function relations(): array
    {
        return [
            'student.user:id,name,email',
            'company:id,company_name',
            'submittedBy:id,name,email',
            'reviewedBy:id,name,email',
            'companyReviewedBy:id,name,email',
            'application:id,internship_title,status,company_id',
        ];
    }

    private function safeFileName(string $name): string
    {
        $base = trim(str_replace(['\\', '/'], '', $name));

        return $base !== '' ? mb_substr($base, 0, 180) : 'dtr';
    }

    private function lockOjtStart(OjtDtrSubmission $row): void
    {
        if ($row->status !== OjtDtrSubmission::STATUS_APPROVED) {
            return;
        }

        \App\Models\PlacementSchedule::query()
            ->where('application_id', $row->application_id)
            ->whereNull('ojt_start_locked_at')
            ->update(['ojt_start_locked_at' => now()]);
    }

    private function displayStatus(OjtDtrSubmission $row): string
    {
        return $row->canonicalStatus();
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(OjtDtrSubmission $row): array
    {
        $row->loadMissing($this->relations());
        $status = $row->canonicalStatus();

        return [
            'id' => (int) $row->id,
            'applicationId' => (int) $row->application_id,
            'internId' => (int) $row->student_id,
            'internName' => $row->student?->user?->name ?? 'Intern',
            'company' => $row->company?->company_name ?? '',
            'internshipTitle' => $row->application?->internship_title ?? '',
            'periodStart' => $row->period_start?->toDateString(),
            'periodEnd' => $row->period_end?->toDateString(),
            'submittedHours' => (float) $row->submitted_hours,
            'status' => $status,
            'displayStatus' => $status,
            'reviewStage' => $row->resolvedReviewStage(),
            'revisionRequired' => $row->status === OjtDtrSubmission::STATUS_REVISION_REQUIRED,
            'remarks' => $row->remarks,
            'rejectionReason' => $row->rejection_reason ?: $row->company_rejection_reason,
            'fileName' => $row->file_name,
            'submittedBy' => $row->submittedBy?->name ?? $row->submittedBy?->email,
            'submittedAt' => $row->created_at?->toIso8601String(),
            'reviewedBy' => $row->reviewedBy?->name ?? $row->reviewedBy?->email,
            'reviewedAt' => $row->reviewed_at?->toIso8601String(),
            'companyReviewedBy' => $row->companyReviewedBy?->name ?? $row->companyReviewedBy?->email,
            'companyReviewedAt' => $row->company_reviewed_at?->toIso8601String(),
            'previousSubmissionId' => $row->previous_submission_id ? (int) $row->previous_submission_id : null,
        ];
    }
}
