<?php

namespace App\Services;

use App\Models\Application;
use App\Models\ApplicationAssessment;
use App\Models\ApplicationInterview;
use App\Models\ApplicationStageEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ApplicationTracker
{
    public function __construct(
        private readonly PlacementScheduleService $placementSchedules,
        private readonly HoursAgreementService $hoursAgreements,
    ) {
    }

    public const STAGE_APPLIED = 'applied';

    public const STAGE_ENDORSED = 'endorsed';

    public const STAGE_INTERVIEW_SCHEDULED = 'interview_scheduled';

    public const STAGE_INTERVIEWED = 'interviewed';

    public const STAGE_ASSESSED = 'assessed';

    public const STAGE_ACCEPTED = 'accepted';

    public const STAGE_REJECTED = 'rejected';

    public const STAGE_SCHOOL_REJECTED = 'school_rejected';

    /**
     * Forward transitions only (rollback handled separately).
     *
     * @var array<string, list<string>>
     */
    public const FORWARD = [
        self::STAGE_APPLIED => [self::STAGE_ENDORSED, self::STAGE_SCHOOL_REJECTED],
        self::STAGE_ENDORSED => [self::STAGE_INTERVIEW_SCHEDULED],
        self::STAGE_INTERVIEW_SCHEDULED => [self::STAGE_INTERVIEWED, self::STAGE_INTERVIEW_SCHEDULED],
        self::STAGE_INTERVIEWED => [self::STAGE_ASSESSED],
        self::STAGE_ASSESSED => [self::STAGE_ACCEPTED, self::STAGE_REJECTED],
        self::STAGE_ACCEPTED => [],
        self::STAGE_REJECTED => [],
        self::STAGE_SCHOOL_REJECTED => [],
    ];

    /**
     * Allowed one-step rollbacks (authorized users + reason).
     *
     * @var array<string, list<string>>
     */
    public const ROLLBACK = [
        self::STAGE_ENDORSED => [self::STAGE_APPLIED],
        self::STAGE_INTERVIEW_SCHEDULED => [self::STAGE_ENDORSED],
        self::STAGE_INTERVIEWED => [self::STAGE_INTERVIEW_SCHEDULED],
        self::STAGE_ASSESSED => [self::STAGE_INTERVIEWED],
        self::STAGE_ACCEPTED => [self::STAGE_ASSESSED],
        self::STAGE_REJECTED => [self::STAGE_ASSESSED],
        self::STAGE_SCHOOL_REJECTED => [self::STAGE_APPLIED],
    ];

    public function normalizeStage(?string $stage): string
    {
        $raw = strtolower(trim((string) $stage));

        return match ($raw) {
            'submitted', 'pending', '' => self::STAGE_APPLIED,
            'approved', 'active' => self::STAGE_ACCEPTED,
            'declined' => self::STAGE_REJECTED,
            default => $raw !== '' ? $raw : self::STAGE_APPLIED,
        };
    }

    public function currentStage(Application $application): string
    {
        if ($application->tracker_stage) {
            return $this->normalizeStage($application->tracker_stage);
        }

        return $this->inferStage($application);
    }

    public function inferStage(Application $application): string
    {
        $status = strtolower(trim((string) $application->status));
        if (in_array($status, ['accepted', 'approved', 'active'], true)) {
            return self::STAGE_ACCEPTED;
        }
        if (in_array($status, ['rejected', 'declined'], true)) {
            return self::STAGE_REJECTED;
        }
        if ($status === 'school_rejected') {
            return self::STAGE_SCHOOL_REJECTED;
        }
        if (! in_array($status, ['endorsed'], true)) {
            return self::STAGE_APPLIED;
        }

        $application->loadMissing(['interviews', 'assessments']);
        if ($application->assessments->isNotEmpty()) {
            return self::STAGE_ASSESSED;
        }
        if ($application->interviews->contains(fn (ApplicationInterview $i) => $i->status === 'completed')) {
            return self::STAGE_INTERVIEWED;
        }
        if ($application->interviews->contains(fn (ApplicationInterview $i) => in_array($i->status, ['proposed', 'confirmed'], true))) {
            return self::STAGE_INTERVIEW_SCHEDULED;
        }

        return self::STAGE_ENDORSED;
    }

    /**
     * @return list<string>
     */
    public function allowedNext(Application $application, bool $includeRollback = false): array
    {
        $current = $this->currentStage($application);
        $forward = self::FORWARD[$current] ?? [];
        if (! $includeRollback) {
            return array_values(array_unique($forward));
        }

        return array_values(array_unique(array_merge($forward, self::ROLLBACK[$current] ?? [])));
    }

    public function assertCanTransition(Application $application, string $toStage, bool $rollback = false): void
    {
        $from = $this->currentStage($application);
        $to = $this->normalizeStage($toStage);

        if ($from === $to && $to === self::STAGE_INTERVIEW_SCHEDULED) {
            // Reschedule keeps the same stage.
            return;
        }

        $allowed = $rollback
            ? (self::ROLLBACK[$from] ?? [])
            : (self::FORWARD[$from] ?? []);

        if (! in_array($to, $allowed, true)) {
            throw ValidationException::withMessages([
                'stage' => ["Cannot move application from {$from} to {$to}."],
            ]);
        }
    }

    public function transition(
        Application $application,
        string $toStage,
        User $actor,
        ?string $reason = null,
        bool $rollback = false,
    ): Application {
        $appRole = $actor->effectiveAppRole();
        if ($appRole === 'student') {
            throw new AccessDeniedHttpException('Students cannot change application stages.');
        }

        $to = $this->normalizeStage($toStage);
        $from = $this->currentStage($application);

        if ($rollback) {
            $reason = trim((string) $reason);
            if ($reason === '') {
                throw ValidationException::withMessages([
                    'reason' => ['A reason is required to move an application back a stage.'],
                ]);
            }
        }

        $this->assertCanTransition($application, $to, $rollback);

        // Phase D: accept blocked until hours agreement is Agreed when a mismatch exists.
        // Stage order unchanged — this is only an accept guard.
        if ($to === self::STAGE_ACCEPTED && ! $rollback) {
            $this->hoursAgreements->assertAcceptAllowed($application);
        }

        return DB::transaction(function () use ($application, $actor, $from, $to, $reason, $rollback, $appRole) {
            $org = $actor->activeOrganization();
            $application->forceFill([
                'tracker_stage' => $to,
                'status' => $this->legacyStatusForStage($to),
            ])->save();

            ApplicationStageEvent::query()->create([
                'application_id' => $application->id,
                'from_stage' => $from,
                'to_stage' => $to,
                'changed_by_user_id' => $actor->id,
                'changed_by_role' => $appRole,
                'organization_id' => $org?->id,
                'organization_name' => $org?->name,
                'reason' => $reason,
                'is_rollback' => $rollback,
            ]);

            if ($to === self::STAGE_ACCEPTED && ! $rollback) {
                $this->placementSchedules->ensureOnAccepted($application);
            }

            return $application->fresh(['interviews', 'assessments', 'stageEvents.changedBy', 'placementSchedule']);
        });
    }

    public function legacyStatusForStage(string $stage): string
    {
        return match ($this->normalizeStage($stage)) {
            self::STAGE_APPLIED => 'submitted',
            self::STAGE_ENDORSED,
            self::STAGE_INTERVIEW_SCHEDULED,
            self::STAGE_INTERVIEWED,
            self::STAGE_ASSESSED => 'endorsed',
            self::STAGE_ACCEPTED => 'accepted',
            self::STAGE_REJECTED => 'rejected',
            self::STAGE_SCHOOL_REJECTED => 'school_rejected',
            default => 'submitted',
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeState(Application $application, ?User $viewer = null): array
    {
        $stage = $this->currentStage($application);
        $application->loadMissing(['stageEvents.changedBy', 'assessments.assessedBy', 'interviews']);

        $lockedAssessment = $application->assessments
            ->sortByDesc(fn (ApplicationAssessment $a) => $a->created_at?->timestamp ?? 0)
            ->first();

        $viewerRole = $viewer?->effectiveAppRole();
        $allowedRollback = self::ROLLBACK[$stage] ?? [];
        // Hosts must not see "Applied" as a rollback target after school endorsement.
        if (in_array($viewerRole, ['company', 'municipal_hall'], true)
            && in_array($stage, [self::STAGE_ENDORSED, self::STAGE_SCHOOL_REJECTED], true)) {
            $allowedRollback = array_values(array_filter(
                $allowedRollback,
                fn (string $target) => $target !== self::STAGE_APPLIED
            ));
        }

        return [
            'applicationId' => (string) $application->id,
            'trackerStage' => $stage,
            'legacyStatus' => $application->status,
            'allowedNext' => $this->allowedNext($application, false),
            'allowedRollback' => $allowedRollback,
            'history' => $application->stageEvents
                ->sortByDesc(fn (ApplicationStageEvent $e) => $e->created_at?->timestamp ?? 0)
                ->values()
                ->map(fn (ApplicationStageEvent $event) => [
                    'id' => (string) $event->id,
                    'fromStage' => $event->from_stage,
                    'toStage' => $event->to_stage,
                    'changedByUserId' => $event->changed_by_user_id ? (string) $event->changed_by_user_id : null,
                    'changedByName' => $event->changedBy?->name ?? $event->changedBy?->email,
                    'changedByRole' => $event->changed_by_role,
                    'organizationId' => $event->organization_id ? (string) $event->organization_id : null,
                    'organizationName' => $event->organization_name,
                    'reason' => $event->reason,
                    'isRollback' => (bool) $event->is_rollback,
                    'createdAt' => $event->created_at?->toIso8601String(),
                ])
                ->all(),
            'assessmentLocked' => $lockedAssessment?->locked_at !== null,
            'viewerRole' => $viewerRole,
        ];
    }

    public function ensureStageColumn(Application $application): void
    {
        if ($application->tracker_stage) {
            return;
        }
        $application->forceFill(['tracker_stage' => $this->inferStage($application)])->save();
    }
}
