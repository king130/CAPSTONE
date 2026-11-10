<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\ApplicationAssessment;
use App\Models\ApplicationInterview;
use App\Services\ApplicationTracker;
use App\Services\PermissionGate;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ApplicationTrackerController extends Controller
{
    public function __construct(
        private readonly ApplicationTracker $tracker,
        private readonly PermissionGate $permissions,
    ) {
    }

    public function show(Request $request, Application $application)
    {
        $user = $request->user();
        if (! $this->canView($user, $application)) {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $this->tracker->ensureStageColumn($application);
        $application->load(['stageEvents.changedBy', 'assessments.assessedBy', 'interviews']);

        return response()->json([
            'data' => $this->tracker->serializeState($application, $user),
        ]);
    }

    public function transition(Request $request, Application $application)
    {
        $user = $request->user();
        $appRole = $user->effectiveAppRole();

        if ($appRole === 'student') {
            return response()->json(['message' => 'Students cannot change application stages.'], Response::HTTP_FORBIDDEN);
        }

        if (! $this->canManage($user, $application)) {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $data = $request->validate([
            'to_stage' => ['required', 'string', 'max:40'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'rollback' => ['sometimes', 'boolean'],
        ]);

        $rollback = (bool) ($data['rollback'] ?? false);
        $to = $this->tracker->normalizeStage($data['to_stage']);
        $from = $this->tracker->currentStage($application);

        // Role-specific stage ownership.
        if ($to === ApplicationTracker::STAGE_ENDORSED || $to === ApplicationTracker::STAGE_SCHOOL_REJECTED) {
            if ($appRole !== 'school' && $appRole !== 'admin') {
                return response()->json(['message' => 'Only schools can endorse or school-reject applications.'], Response::HTTP_FORBIDDEN);
            }
        }

        // School endorsement may only be undone by the school (or admin) — not by the host.
        if ($rollback && $to === ApplicationTracker::STAGE_APPLIED
            && in_array($from, [ApplicationTracker::STAGE_ENDORSED, ApplicationTracker::STAGE_SCHOOL_REJECTED], true)) {
            if ($appRole !== 'school' && $appRole !== 'admin') {
                return response()->json([
                    'message' => 'Only the school can roll an application back to Applied after endorsement.',
                ], Response::HTTP_FORBIDDEN);
            }
        }

        // Rolling back to Endorsed undoes host interview scheduling — host or admin only
        // (school coordinating partners should not wipe host interview progress).
        if ($rollback && $to === ApplicationTracker::STAGE_ENDORSED
            && $from === ApplicationTracker::STAGE_INTERVIEW_SCHEDULED) {
            if ($appRole !== 'company' && $appRole !== 'admin' && ! $this->schoolHostsInternship($user, $application)) {
                return response()->json([
                    'message' => 'Only the host can roll interview scheduling back to Endorsed.',
                ], Response::HTTP_FORBIDDEN);
            }
        }

        if (in_array($to, [
            ApplicationTracker::STAGE_INTERVIEWED,
            ApplicationTracker::STAGE_ACCEPTED,
            ApplicationTracker::STAGE_REJECTED,
        ], true)) {
            if ($appRole !== 'company' && $appRole !== 'admin' && ! $this->schoolHostsInternship($user, $application)) {
                return response()->json(['message' => 'Only the host can advance this stage.'], Response::HTTP_FORBIDDEN);
            }
        }

        if ($to === ApplicationTracker::STAGE_REJECTED && trim((string) ($data['reason'] ?? '')) === '') {
            throw ValidationException::withMessages([
                'reason' => ['A rejection reason is required.'],
            ]);
        }

        if ($to === ApplicationTracker::STAGE_INTERVIEWED) {
            $hasInterview = ApplicationInterview::query()
                ->where('application_id', $application->id)
                ->whereIn('status', ['proposed', 'confirmed', 'completed'])
                ->exists();
            if (! $hasInterview) {
                throw ValidationException::withMessages([
                    'stage' => ['Mark interview complete only after an interview has been scheduled.'],
                ]);
            }
            ApplicationInterview::query()
                ->where('application_id', $application->id)
                ->whereIn('status', ['proposed', 'confirmed'])
                ->update(['status' => 'completed']);
        }

        if ($to === ApplicationTracker::STAGE_ACCEPTED || $to === ApplicationTracker::STAGE_REJECTED) {
            if ($this->tracker->currentStage($application) !== ApplicationTracker::STAGE_ASSESSED) {
                throw ValidationException::withMessages([
                    'stage' => ['Accept or reject is only allowed after assessment.'],
                ]);
            }
        }

        try {
            $updated = $this->tracker->transition(
                $application,
                $to,
                $user,
                $data['reason'] ?? null,
                $rollback,
            );
        } catch (ConflictHttpException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'active_placement_conflict',
            ], Response::HTTP_CONFLICT);
        }

        return response()->json([
            'data' => $this->tracker->serializeState($updated, $user),
        ]);
    }

    public function storeAssessment(Request $request, Application $application)
    {
        $user = $request->user();
        $appRole = $user->effectiveAppRole();

        if ($appRole === 'student') {
            return response()->json(['message' => 'Students cannot submit assessments.'], Response::HTTP_FORBIDDEN);
        }

        if ($appRole !== 'admin' && ! $this->permissions->userCanAny($user, ['org.manage_assessments', 'org.approve_applications'])) {
            return response()->json(['message' => 'Missing permission to manage assessments.'], Response::HTTP_FORBIDDEN);
        }

        if (! $this->canManage($user, $application)) {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $this->tracker->ensureStageColumn($application);
        if ($this->tracker->currentStage($application) !== ApplicationTracker::STAGE_INTERVIEWED) {
            throw ValidationException::withMessages([
                'stage' => ['Assessment opens only after the interview is marked complete.'],
            ]);
        }

        $existing = ApplicationAssessment::query()
            ->where('application_id', $application->id)
            ->whereNotNull('locked_at')
            ->latest('id')
            ->first();
        if ($existing) {
            throw ValidationException::withMessages([
                'assessment' => ['Assessment is locked. Authorized users must unlock with a reason to edit.'],
            ]);
        }

        $data = $request->validate([
            'technical' => ['required', 'numeric', 'min:1', 'max:5'],
            'communication' => ['required', 'numeric', 'min:1', 'max:5'],
            'attitude' => ['required', 'numeric', 'min:1', 'max:5'],
            'comments' => ['nullable', 'string', 'max:5000'],
        ]);

        $scores = [
            'technical' => (float) $data['technical'],
            'communication' => (float) $data['communication'],
            'attitude' => (float) $data['attitude'],
        ];
        $overall = round(array_sum($scores) / 3, 2);

        $assessment = ApplicationAssessment::query()->create([
            'application_id' => $application->id,
            'assessed_by_user_id' => $user->id,
            'stage' => 'final',
            'rubric_scores' => $scores,
            'overall_score' => $overall,
            'comments' => $data['comments'] ?? null,
            'locked_at' => now(),
            'lock_reason' => null,
        ]);

        $this->tracker->transition($application, ApplicationTracker::STAGE_ASSESSED, $user, 'Assessment submitted');

        $assessment->load('assessedBy:id,name,email');

        return response()->json([
            'data' => [
                'id' => (string) $assessment->id,
                'applicationId' => (string) $assessment->application_id,
                'stage' => $assessment->stage,
                'rubricScores' => $assessment->rubric_scores,
                'overallScore' => (float) $assessment->overall_score,
                'comments' => $assessment->comments,
                'lockedAt' => $assessment->locked_at?->toIso8601String(),
                'assessedByName' => $assessment->assessedBy?->name,
                'tracker' => $this->tracker->serializeState($application->fresh(['stageEvents.changedBy', 'assessments', 'interviews']), $user),
            ],
        ], Response::HTTP_CREATED);
    }

    public function unlockAssessment(Request $request, Application $application, ApplicationAssessment $assessment)
    {
        $user = $request->user();
        $appRole = $user->effectiveAppRole();

        if ($appRole === 'student') {
            return response()->json(['message' => 'Students cannot unlock assessments.'], Response::HTTP_FORBIDDEN);
        }

        if ((int) $assessment->application_id !== (int) $application->id) {
            return response()->json(['message' => 'Assessment does not belong to this application.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (! $this->canManage($user, $application)) {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        if ($appRole !== 'admin' && ! $this->permissions->userCanAny($user, ['org.manage_assessments', 'org.approve_applications'])) {
            return response()->json(['message' => 'Missing permission to unlock assessments.'], Response::HTTP_FORBIDDEN);
        }

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $assessment->forceFill([
            'locked_at' => null,
            'lock_reason' => $data['reason'],
        ])->save();

        if ($this->tracker->currentStage($application) === ApplicationTracker::STAGE_ASSESSED) {
            $this->tracker->transition($application, ApplicationTracker::STAGE_INTERVIEWED, $user, $data['reason'], true);
        }

        return response()->json([
            'data' => [
                'id' => (string) $assessment->id,
                'lockedAt' => null,
                'lockReason' => $assessment->lock_reason,
                'tracker' => $this->tracker->serializeState($application->fresh(['stageEvents.changedBy', 'assessments', 'interviews']), $user),
            ],
        ]);
    }

    private function canView($user, Application $application): bool
    {
        return $this->canAccess($user, $application, false);
    }

    private function canManage($user, Application $application): bool
    {
        return $this->canAccess($user, $application, true);
    }

    private function canAccess($user, Application $application, bool $requireManagePermission): bool
    {
        $appRole = $user->effectiveAppRole();
        if ($appRole === 'admin') {
            return true;
        }

        if ($appRole === 'student') {
            return $user->student && (int) $application->student_id === (int) $user->student->id;
        }

        if ($requireManagePermission && ! $this->permissions->userCanAny($user, [
            'org.approve_applications',
            'org.manage_applications',
            'org.manage_assessments',
        ])) {
            return false;
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

    private function schoolHostsInternship($user, Application $application): bool
    {
        $school = $user->organizationSchool() ?? $user->school;
        $internship = $application->internship;
        if (! $school || ! $internship || (int) $internship->school_id !== (int) $school->id) {
            return false;
        }

        return strtolower(trim((string) $internship->host_type)) === 'school'
            || ($internship->company_id === null && $internship->school_id !== null);
    }
}
