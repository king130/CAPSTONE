<?php

namespace App\Services;

use App\Models\Application;
use App\Models\ApplicationInterview;
use App\Models\Notification;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class PlacementExclusivityService
{
    public const STATUS_WITHDRAWN_PLACED_ELSEWHERE = 'withdrawn_placed_elsewhere';

    public const REASON_PLACED_ELSEWHERE = 'Placed elsewhere';

    public const CLOSE_CANCELLED = 'cancelled';

    public const CLOSE_DECLINED = 'declined';

    public const CLOSE_ENDED = 'ended';

    public const CLOSE_COMPLETED = 'completed';

    /**
     * Open pipeline stages that should be withdrawn when the intern is placed elsewhere.
     *
     * @var list<string>
     */
    public const OPEN_STAGES = [
        ApplicationTracker::STAGE_APPLIED,
        ApplicationTracker::STAGE_ENDORSED,
        ApplicationTracker::STAGE_INTERVIEW_SCHEDULED,
        ApplicationTracker::STAGE_INTERVIEWED,
        ApplicationTracker::STAGE_ASSESSED,
    ];

    /**
     * Legacy statuses treated as still open for withdrawal.
     *
     * @var list<string>
     */
    public const OPEN_STATUSES = [
        'submitted',
        'pending',
        'endorsed',
    ];

    public function isActivePlacement(Application $application): bool
    {
        $stage = $this->normalizeStage($application->tracker_stage ?: $application->status);
        if ($stage !== ApplicationTracker::STAGE_ACCEPTED) {
            return false;
        }

        return $application->placement_closed_at === null
            && $application->active_placement_key !== null;
    }

    public function findActivePlacement(int $studentId): ?Application
    {
        return Application::query()
            ->with([
                'internship:id,title,company_id,school_id,host_type,host_name',
                'internship.company:id,user_id,company_name',
                'internship.school:id,institution_name',
                'company:id,user_id,company_name',
            ])
            ->where('student_id', $studentId)
            ->where('tracker_stage', ApplicationTracker::STAGE_ACCEPTED)
            ->whereNull('placement_closed_at')
            ->whereNotNull('active_placement_key')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return array{hostName: string, internshipTitle: string, applicationId: string}|null
     */
    public function activePlacementSummary(int $studentId): ?array
    {
        $placement = $this->findActivePlacement($studentId);
        if (! $placement) {
            return null;
        }

        return [
            'applicationId' => (string) $placement->id,
            'internshipTitle' => (string) ($placement->internship_title ?: $placement->internship?->title ?: 'your placement'),
            'hostName' => $this->hostNameFor($placement),
            'hostType' => $this->hostTypeFor($placement),
            'message' => $this->blockMessage($placement),
        ];
    }

    public function blockMessage(Application $placement): string
    {
        $host = $this->hostNameFor($placement);
        $title = trim((string) ($placement->internship_title ?: $placement->internship?->title ?: 'an internship'));

        return 'You already have an accepted placement at '.$host.' ('.$title.'). Finish or cancel that placement before applying elsewhere.';
    }

    public function assertStudentCanApply(Student $student): void
    {
        $placement = $this->findActivePlacement((int) $student->id);
        if (! $placement) {
            return;
        }

        throw ValidationException::withMessages([
            'internship_id' => [$this->blockMessage($placement)],
        ]);
    }

    /**
     * Call inside the same DB transaction that marks the application accepted.
     * Locks the student row and open applications so concurrent accepts cannot both succeed.
     *
     * @return list<Application> withdrawn applications
     */
    public function claimPlacementAndWithdrawOthers(Application $accepted, User $actor): array
    {
        $studentId = (int) $accepted->student_id;

        Student::query()->whereKey($studentId)->lockForUpdate()->first();

        $existing = Application::query()
            ->where('student_id', $studentId)
            ->where('tracker_stage', ApplicationTracker::STAGE_ACCEPTED)
            ->whereNull('placement_closed_at')
            ->whereNotNull('active_placement_key')
            ->where('id', '!=', $accepted->id)
            ->lockForUpdate()
            ->first();

        if ($existing) {
            throw new ConflictHttpException(
                'This intern already has an active placement at '.$this->hostNameFor($existing).'. Only one accepted placement is allowed.'
            );
        }

        try {
            $accepted->forceFill([
                'active_placement_key' => $studentId,
                'placement_closed_at' => null,
                'placement_close_reason' => null,
                'withdrawal_reason' => null,
            ])->save();
        } catch (QueryException $e) {
            if ($this->isUniqueConstraintViolation($e)) {
                throw new ConflictHttpException(
                    'Another host just accepted this intern. Only one accepted placement is allowed.'
                );
            }
            throw $e;
        }

        $openApps = Application::query()
            ->with([
                'internship:id,title,company_id,school_id,host_type,host_name',
                'internship.company:id,user_id,company_name',
                'company:id,user_id,company_name',
                'student:id,user_id',
            ])
            ->where('student_id', $studentId)
            ->where('id', '!=', $accepted->id)
            ->where(function ($query) {
                $query->whereIn('tracker_stage', self::OPEN_STAGES)
                    ->orWhere(function ($legacy) {
                        $legacy->whereNull('tracker_stage')
                            ->whereIn('status', self::OPEN_STATUSES);
                    });
            })
            ->lockForUpdate()
            ->get();

        $withdrawn = [];
        foreach ($openApps as $other) {
            $from = $this->normalizeStage($other->tracker_stage ?: $other->status);
            $other->forceFill([
                'tracker_stage' => ApplicationTracker::STAGE_WITHDRAWN_PLACED_ELSEWHERE,
                'status' => self::STATUS_WITHDRAWN_PLACED_ELSEWHERE,
                'withdrawal_reason' => self::REASON_PLACED_ELSEWHERE,
                'active_placement_key' => null,
            ])->save();

            \App\Models\ApplicationStageEvent::query()->create([
                'application_id' => $other->id,
                'from_stage' => $from,
                'to_stage' => ApplicationTracker::STAGE_WITHDRAWN_PLACED_ELSEWHERE,
                'changed_by_user_id' => $actor->id,
                'changed_by_role' => $actor->effectiveAppRole(),
                'organization_id' => $actor->activeOrganization()?->id,
                'organization_name' => $actor->activeOrganization()?->name,
                'reason' => self::REASON_PLACED_ELSEWHERE,
                'is_rollback' => false,
            ]);

            $this->cancelOpenInterviews($other);
            $withdrawn[] = $other->fresh([
                'internship.company',
                'company',
                'student',
            ]);
        }

        $this->notifyPlacementClaimed($accepted->fresh([
            'internship.company',
            'internship.school',
            'student',
            'company',
        ]), $withdrawn);

        return $withdrawn;
    }

    public function closePlacement(Application $application, string $reason, User $actor): Application
    {
        $reason = strtolower(trim($reason));
        if (! in_array($reason, [
            self::CLOSE_CANCELLED,
            self::CLOSE_DECLINED,
            self::CLOSE_ENDED,
            self::CLOSE_COMPLETED,
        ], true)) {
            throw ValidationException::withMessages([
                'reason' => ['Use cancelled, declined, ended, or completed.'],
            ]);
        }

        $current = $this->normalizeStage($application->tracker_stage ?: $application->status);
        if (! $this->isActivePlacement($application) && $current !== ApplicationTracker::STAGE_ACCEPTED) {
            throw ValidationException::withMessages([
                'placement' => ['This application is not an active placement.'],
            ]);
        }

        $stage = match ($reason) {
            self::CLOSE_DECLINED => ApplicationTracker::STAGE_PLACEMENT_DECLINED,
            self::CLOSE_ENDED, self::CLOSE_COMPLETED => ApplicationTracker::STAGE_PLACEMENT_ENDED,
            default => ApplicationTracker::STAGE_PLACEMENT_CANCELLED,
        };

        $from = $current;

        return DB::transaction(function () use ($application, $actor, $reason, $stage, $from) {
            Student::query()->whereKey($application->student_id)->lockForUpdate()->first();

            $application->forceFill([
                'tracker_stage' => $stage,
                'status' => $stage,
                'placement_closed_at' => now(),
                'placement_close_reason' => $reason,
                'active_placement_key' => null,
            ])->save();

            \App\Models\ApplicationStageEvent::query()->create([
                'application_id' => $application->id,
                'from_stage' => $from,
                'to_stage' => $stage,
                'changed_by_user_id' => $actor->id,
                'changed_by_role' => $actor->effectiveAppRole(),
                'organization_id' => $actor->activeOrganization()?->id,
                'organization_name' => $actor->activeOrganization()?->name,
                'reason' => 'Placement '.$reason,
                'is_rollback' => false,
            ]);

            $fresh = $application->fresh([
                'internship.company',
                'internship.school',
                'student',
                'company',
            ]);

            $this->notifyPlacementClosed($fresh, $reason, $actor);

            return $fresh;
        });
    }

    private function cancelOpenInterviews(Application $application): void
    {
        $open = ApplicationInterview::query()
            ->where('application_id', $application->id)
            ->whereNotIn('status', ['cancelled', 'completed'])
            ->get();

        foreach ($open as $interview) {
            $notes = trim((string) ($interview->notes ?? ''));
            $suffix = 'Cancelled: intern placed elsewhere.';
            $interview->forceFill([
                'status' => 'cancelled',
                'notes' => $notes === '' ? $suffix : $notes."\n".$suffix,
            ])->save();
        }
    }

    /**
     * @param  list<Application>  $withdrawn
     */
    private function notifyPlacementClaimed(Application $accepted, array $withdrawn): void
    {
        $host = $this->hostNameFor($accepted);
        $title = trim((string) ($accepted->internship_title ?: $accepted->internship?->title ?: 'an internship'));
        $studentUserId = $accepted->student?->user_id;

        if ($studentUserId) {
            $this->notify(
                (int) $studentUserId,
                'Placement confirmed',
                'You were accepted at '.$host.' for '.$title.'. Your other open applications were withdrawn.',
                [
                    'type' => 'placement_accepted',
                    'applicationId' => (string) $accepted->id,
                    'redirectTo' => '/intern/applications',
                ]
            );
        }

        foreach ($withdrawn as $other) {
            $hostUserId = $other->company?->user_id ?? $other->internship?->company?->user_id;
            if (! $hostUserId) {
                continue;
            }
            $otherTitle = trim((string) ($other->internship_title ?: $other->internship?->title ?: 'your opening'));
            $this->notify(
                (int) $hostUserId,
                'Applicant placed elsewhere',
                trim(($accepted->student_name ?: 'An intern').' was accepted elsewhere. Their application for '.$otherTitle.' is now Withdrawn — placed elsewhere.'),
                [
                    'type' => 'applicant_placed_elsewhere',
                    'applicationId' => (string) $other->id,
                    'redirectTo' => '/dashboard/applicants',
                ]
            );
        }
    }

    private function notifyPlacementClosed(Application $application, string $reason, User $actor): void
    {
        $host = $this->hostNameFor($application);
        $title = trim((string) ($application->internship_title ?: $application->internship?->title ?: 'the internship'));
        $studentUserId = $application->student?->user_id;
        $hostUserId = $application->company?->user_id ?? $application->internship?->company?->user_id;

        $label = match ($reason) {
            self::CLOSE_DECLINED => 'declined',
            self::CLOSE_ENDED => 'ended',
            self::CLOSE_COMPLETED => 'completed',
            default => 'cancelled',
        };

        if ($studentUserId && (int) $actor->id !== (int) $studentUserId) {
            $this->notify(
                (int) $studentUserId,
                'Placement '.$label,
                'Your placement at '.$host.' for '.$title.' was '.$label.'. You can apply to other openings again.',
                [
                    'type' => 'placement_closed',
                    'applicationId' => (string) $application->id,
                    'redirectTo' => '/intern/opportunities',
                ]
            );
        }

        if ($hostUserId && (int) $actor->id !== (int) $hostUserId) {
            $this->notify(
                (int) $hostUserId,
                'Placement '.$label,
                trim(($application->student_name ?: 'An intern')."'s placement for ".$title.' was '.$label.'.'),
                [
                    'type' => 'placement_closed',
                    'applicationId' => (string) $application->id,
                    'redirectTo' => '/dashboard/applicants',
                ]
            );
        }
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function notify(int $userId, string $title, string $body, array $metadata): void
    {
        Notification::query()->create([
            'user_id' => $userId,
            'title' => $title,
            'body' => $body,
            'channel' => 'in_app',
            'metadata' => $metadata,
        ]);
    }

    public function hostNameFor(Application $application): string
    {
        $hostType = $this->hostTypeFor($application);
        if ($hostType === 'school') {
            return (string) ($application->internship?->host_name
                ?: $application->internship?->school?->institution_name
                ?: 'your school');
        }

        return (string) ($application->internship?->host_name
            ?: $application->internship?->company?->company_name
            ?: $application->company?->company_name
            ?: 'the host');
    }

    public function hostTypeFor(Application $application): string
    {
        $raw = strtolower(trim((string) ($application->internship?->host_type ?: '')));
        if ($raw !== '') {
            return $raw;
        }

        return $application->internship?->company_id ? 'company' : 'school';
    }

    private function isUniqueConstraintViolation(QueryException $e): bool
    {
        $sqlState = $e->errorInfo[0] ?? '';
        $driverCode = (int) ($e->errorInfo[1] ?? 0);

        return $sqlState === '23000' || $driverCode === 1062 || str_contains(strtolower($e->getMessage()), 'unique');
    }

    private function normalizeStage(?string $stage): string
    {
        $raw = strtolower(trim((string) $stage));

        return match ($raw) {
            'submitted', 'pending', '' => ApplicationTracker::STAGE_APPLIED,
            'approved', 'active' => ApplicationTracker::STAGE_ACCEPTED,
            'declined' => ApplicationTracker::STAGE_REJECTED,
            default => $raw !== '' ? $raw : ApplicationTracker::STAGE_APPLIED,
        };
    }
}
