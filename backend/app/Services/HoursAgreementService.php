<?php

namespace App\Services;

use App\Models\Application;
use App\Models\ApplicationHoursAgreement;
use App\Models\ApplicationHoursAgreementEvent;
use App\Models\Internship;
use App\Models\Notification;
use App\Models\OjtDtrSubmission;
use App\Models\User;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class HoursAgreementService
{
    public const MAX_HOURS = 10000;

    public function __construct(private readonly RequiredOjtHoursAccessor $hoursAccessor)
    {
    }

    public function mismatchExists(?int $schoolHours, ?int $hostHours): bool
    {
        if ($hostHours === null || $hostHours < 1) {
            return false;
        }
        if ($schoolHours === null || $schoolHours < 1) {
            return false;
        }

        return (int) $schoolHours !== (int) $hostHours;
    }

    /**
     * Create a pending agreement at apply time when host hours differ from school snapshot.
     */
    public function createOnApplyIfNeeded(Application $application, Internship $internship): ?ApplicationHoursAgreement
    {
        $schoolHours = $application->required_ojt_hours !== null ? (int) $application->required_ojt_hours : null;
        $hostHours = $internship->host_required_hours !== null ? (int) $internship->host_required_hours : null;

        if (! $this->mismatchExists($schoolHours, $hostHours)) {
            return null;
        }

        return DB::transaction(function () use ($application, $schoolHours, $hostHours) {
            $agreement = ApplicationHoursAgreement::query()->create([
                'application_id' => $application->id,
                'school_required_hours' => $schoolHours,
                'host_offered_hours' => $hostHours,
                'proposed_hours' => null,
                'agreed_hours' => null,
                'status' => ApplicationHoursAgreement::STATUS_PENDING,
            ]);

            $this->recordEvent($agreement, 'created', null, ApplicationHoursAgreement::STATUS_PENDING, null, null, null, null, null);

            $this->notifyParties(
                $application,
                'Hours agreement needed',
                'School and host hours differ (school '.$schoolHours.'h vs host '.$hostHours.'h). An agreement is required before acceptance.',
                'hours_agreement_created'
            );

            return $agreement;
        });
    }

    public function assertAcceptAllowed(Application $application): void
    {
        $agreement = $application->hoursAgreement()->first();
        if (! $agreement) {
            return;
        }

        if ($agreement->status === ApplicationHoursAgreement::STATUS_AGREED) {
            return;
        }

        throw ValidationException::withMessages([
            'hours_agreement' => [
                'Hours still differ between school and host. Complete the hours agreement (status Agreed) before accepting this application.',
            ],
        ]);
    }

    /**
     * @return array{side: string, school: \App\Models\School, hostCompany: ?\App\Models\Company}
     */
    public function assertCanAct(User $user, Application $application, bool $mutating): array
    {
        $application->loadMissing(['student.school', 'internship.company', 'internship.school']);
        $role = $user->effectiveAppRole();
        $school = $user->organizationSchool() ?? $user->school;
        $company = $user->organizationCompany() ?? $user->company;

        $studentSchool = $application->student?->school;
        // Match ApplicationController::index school scoping (school_id OR subscription_code).
        $ownsStudent = $school && $application->student && (
            ((int) ($application->student->school_id ?? 0) === (int) $school->id)
            || (
                $school->subscription_code
                && $application->student->school_subscription_code
                && (string) $application->student->school_subscription_code === (string) $school->subscription_code
            )
        );

        $internship = $application->internship;
        $isCompanyHost = $internship
            && $internship->host_type !== 'school'
            && $company
            && $internship->company_id
            && (int) $internship->company_id === (int) $company->id;
        $isSchoolHost = $internship
            && $internship->host_type === 'school'
            && $school
            && $internship->school_id
            && (int) $internship->school_id === (int) $school->id;

        if ($role === 'student') {
            if ($mutating) {
                throw new AccessDeniedHttpException('Students can view the hours agreement but cannot confirm or change it.');
            }
            $student = $user->student;
            if (! $student || (int) $student->id !== (int) $application->student_id) {
                throw new AccessDeniedHttpException('Forbidden.');
            }

            return ['side' => 'student', 'school' => $studentSchool, 'hostCompany' => $internship?->company];
        }

        // Prefer school-of-student for school role; otherwise posting host.
        if ($ownsStudent && $role === 'school') {
            return ['side' => ApplicationHoursAgreement::SIDE_SCHOOL, 'school' => $school, 'hostCompany' => $internship?->company];
        }

        if ($isCompanyHost || $isSchoolHost) {
            return ['side' => ApplicationHoursAgreement::SIDE_HOST, 'school' => $studentSchool, 'hostCompany' => $internship?->company];
        }

        throw new AccessDeniedHttpException('Only the student\'s school and the posting host can access this hours agreement.');
    }

    public function propose(Application $application, User $actor, int $hours, ?string $note = null, ?string $reason = null): ApplicationHoursAgreement
    {
        $ctx = $this->assertCanAct($actor, $application, true);
        if ($ctx['side'] === 'student') {
            throw new AccessDeniedHttpException('Students cannot propose hours.');
        }

        $this->assertValidHours($hours);
        $agreement = $this->requireAgreement($application);

        if ($agreement->status === ApplicationHoursAgreement::STATUS_DECLINED) {
            throw ValidationException::withMessages([
                'status' => ['This hours agreement was declined. Contact support if you need to reopen it.'],
            ]);
        }

        $hasApprovedDtr = $this->hasApprovedDtr($application);
        if ($agreement->isAgreed() && $hasApprovedDtr) {
            $reason = trim((string) $reason);
            if ($reason === '') {
                throw ValidationException::withMessages([
                    'reason' => ['A reason is required to change hours after the first approved DTR.'],
                ]);
            }
            $note = trim(($note ? $note.' ' : '').'Re-agreement reason: '.$reason);
        }

        return DB::transaction(function () use ($agreement, $actor, $ctx, $hours, $note) {
            $from = $agreement->status;
            $side = $ctx['side'];

            $agreement->forceFill([
                'proposed_hours' => $hours,
                'proposed_by_side' => $side,
                'proposed_by_user_id' => $actor->id,
                'status' => ApplicationHoursAgreement::STATUS_PENDING,
                'agreed_hours' => null,
                'note' => $note,
                'school_confirmed_at' => $side === ApplicationHoursAgreement::SIDE_SCHOOL ? now() : null,
                'school_confirmed_by_user_id' => $side === ApplicationHoursAgreement::SIDE_SCHOOL ? $actor->id : null,
                'host_confirmed_at' => $side === ApplicationHoursAgreement::SIDE_HOST ? now() : null,
                'host_confirmed_by_user_id' => $side === ApplicationHoursAgreement::SIDE_HOST ? $actor->id : null,
            ])->save();

            $this->recordEvent(
                $agreement,
                'proposed',
                $from,
                ApplicationHoursAgreement::STATUS_PENDING,
                $hours,
                null,
                $actor,
                $side,
                $note
            );

            $this->notifyOtherSide(
                $agreement->application,
                $side,
                'Hours proposal updated',
                $actor->name.' proposed '.$hours.' OJT hours. Confirm or propose a different value.',
                'hours_agreement_proposed'
            );

            return $agreement->fresh(['events.changedBy']);
        });
    }

    public function confirm(Application $application, User $actor, ?string $note = null): ApplicationHoursAgreement
    {
        $ctx = $this->assertCanAct($actor, $application, true);
        if ($ctx['side'] === 'student') {
            throw new AccessDeniedHttpException('Students cannot confirm hours.');
        }

        $agreement = $this->requireAgreement($application);
        if (! $agreement->isPending()) {
            throw ValidationException::withMessages([
                'status' => ['Only a pending hours agreement can be confirmed.'],
            ]);
        }
        if ($agreement->proposed_hours === null || (int) $agreement->proposed_hours < 1) {
            throw ValidationException::withMessages([
                'proposed_hours' => ['A proposal is required before confirming.'],
            ]);
        }

        return DB::transaction(function () use ($agreement, $actor, $ctx, $note, $application) {
            $side = $ctx['side'];
            if ($side === ApplicationHoursAgreement::SIDE_SCHOOL) {
                $agreement->school_confirmed_at = now();
                $agreement->school_confirmed_by_user_id = $actor->id;
            } else {
                $agreement->host_confirmed_at = now();
                $agreement->host_confirmed_by_user_id = $actor->id;
            }
            if ($note) {
                $agreement->note = $note;
            }
            $agreement->save();

            $both = $agreement->school_confirmed_at && $agreement->host_confirmed_at;
            if ($both) {
                $agreed = (int) $agreement->proposed_hours;
                $from = $agreement->status;
                $agreement->forceFill([
                    'status' => ApplicationHoursAgreement::STATUS_AGREED,
                    'agreed_hours' => $agreed,
                ])->save();

                // Placement required hours become the agreed value (this application only).
                $application->forceFill(['required_ojt_hours' => $agreed])->save();

                $this->recordEvent(
                    $agreement,
                    'agreed',
                    $from,
                    ApplicationHoursAgreement::STATUS_AGREED,
                    $agreed,
                    $agreed,
                    $actor,
                    $side,
                    $note ?? 'Both sides confirmed. Required hours set to '.$agreed.'.'
                );

                $this->notifyParties(
                    $application,
                    'Hours agreement confirmed',
                    'Agreed OJT hours for this placement: '.$agreed.'h.',
                    'hours_agreement_agreed'
                );
            } else {
                $this->recordEvent(
                    $agreement,
                    'confirmed',
                    ApplicationHoursAgreement::STATUS_PENDING,
                    ApplicationHoursAgreement::STATUS_PENDING,
                    $agreement->proposed_hours,
                    null,
                    $actor,
                    $side,
                    $note
                );
                $this->notifyOtherSide(
                    $application,
                    $side,
                    'Hours agreement awaiting your confirmation',
                    $actor->name.' confirmed '.$agreement->proposed_hours.'h. Confirm to finalize.',
                    'hours_agreement_confirmed'
                );
            }

            return $agreement->fresh(['events.changedBy']);
        });
    }

    public function decline(Application $application, User $actor, ?string $note = null): ApplicationHoursAgreement
    {
        $ctx = $this->assertCanAct($actor, $application, true);
        if ($ctx['side'] === 'student') {
            throw new AccessDeniedHttpException('Students cannot decline hours agreements.');
        }

        $agreement = $this->requireAgreement($application);
        if ($agreement->isAgreed() && $this->hasApprovedDtr($application)) {
            throw ValidationException::withMessages([
                'status' => ['Cannot decline after hours were agreed and DTR hours were approved. Propose a re-agreement with a reason instead.'],
            ]);
        }

        return DB::transaction(function () use ($agreement, $actor, $ctx, $note, $application) {
            $from = $agreement->status;
            $agreement->forceFill([
                'status' => ApplicationHoursAgreement::STATUS_DECLINED,
                'note' => $note,
                'school_confirmed_at' => null,
                'host_confirmed_at' => null,
                'school_confirmed_by_user_id' => null,
                'host_confirmed_by_user_id' => null,
            ])->save();

            $this->recordEvent(
                $agreement,
                'declined',
                $from,
                ApplicationHoursAgreement::STATUS_DECLINED,
                $agreement->proposed_hours,
                null,
                $actor,
                $ctx['side'],
                $note
            );

            $this->notifyParties(
                $application,
                'Hours agreement declined',
                'The hours agreement was declined.'.($note ? ' Note: '.$note : ''),
                'hours_agreement_declined'
            );

            return $agreement->fresh(['events.changedBy']);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(
        ?ApplicationHoursAgreement $agreement,
        ?Application $application = null,
        ?Internship $internship = null,
        bool $includeHistory = true,
    ): array {
        $app = $application ?? $agreement?->application;
        $job = $internship ?? $app?->internship;
        $schoolHours = self::positiveHoursOrNull($app?->required_ojt_hours);
        // Prefer agreement school snapshot when present; fall back to application course hours.
        if ($agreement) {
            $schoolHours = self::positiveHoursOrNull($agreement->school_required_hours) ?? $schoolHours;
        }
        $hostHours = self::positiveHoursOrNull($job?->host_required_hours);
        if ($agreement) {
            $hostHours = self::positiveHoursOrNull($agreement->host_offered_hours) ?? $hostHours;
        }

        $mismatch = $this->mismatchExists($schoolHours, $hostHours);
        $effective = $app ? $this->hoursAccessor->forApplication($app) : null;

        $payload = [
            'hoursMismatch' => $mismatch,
            'schoolRequiredHours' => $schoolHours,
            'hostRequiredHours' => $hostHours,
            'effectiveRequiredHours' => $effective,
            'agreement' => null,
        ];

        if (! $agreement) {
            return $payload;
        }

        if ($includeHistory) {
            $agreement->loadMissing(['events.changedBy']);
        }

        $payload['agreement'] = [
            'id' => (string) $agreement->id,
            'applicationId' => (string) $agreement->application_id,
            'status' => $agreement->status,
            'schoolRequiredHours' => self::positiveHoursOrNull($agreement->school_required_hours)
                ?? self::positiveHoursOrNull($app?->required_ojt_hours),
            'hostOfferedHours' => self::positiveHoursOrNull($agreement->host_offered_hours)
                ?? self::positiveHoursOrNull($job?->host_required_hours),
            'proposedHours' => $agreement->proposed_hours !== null ? (int) $agreement->proposed_hours : null,
            'agreedHours' => $agreement->agreed_hours !== null ? (int) $agreement->agreed_hours : null,
            'proposedBySide' => $agreement->proposed_by_side,
            'schoolConfirmed' => $agreement->school_confirmed_at !== null,
            'hostConfirmed' => $agreement->host_confirmed_at !== null,
            'note' => $agreement->note,
            'hasApprovedDtr' => $app ? $this->hasApprovedDtr($app) : false,
            'history' => $includeHistory
                ? $agreement->events
                    ->sortByDesc(fn (ApplicationHoursAgreementEvent $e) => $e->created_at?->timestamp ?? 0)
                    ->values()
                    ->map(fn (ApplicationHoursAgreementEvent $event) => [
                        'id' => (string) $event->id,
                        'action' => $event->action,
                        'fromStatus' => $event->from_status,
                        'toStatus' => $event->to_status,
                        'proposedHours' => $event->proposed_hours,
                        'agreedHours' => $event->agreed_hours,
                        'changedByName' => $event->changedBy?->name ?? $event->changedBy?->email,
                        'changedBySide' => $event->changed_by_side,
                        'changedByRole' => $event->changed_by_role,
                        'note' => $event->note,
                        'createdAt' => $event->created_at?->toIso8601String(),
                    ])
                    ->all()
                : [],
        ];

        return $payload;
    }

    private function requireAgreement(Application $application): ApplicationHoursAgreement
    {
        $agreement = $application->hoursAgreement()->first();
        if (! $agreement) {
            throw new HttpException(Response::HTTP_NOT_FOUND, 'No hours agreement exists for this application.');
        }

        return $agreement;
    }

    /**
     * Match frontend "Use school/host hours" enable rule: missing/zero hours stay null (not 0).
     */
    private static function positiveHoursOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_numeric($value)) {
            return null;
        }
        $hours = (int) $value;

        return $hours >= 1 ? $hours : null;
    }

    private function assertValidHours(int $hours): void
    {
        if ($hours < 1 || $hours > self::MAX_HOURS) {
            throw ValidationException::withMessages([
                'hours' => ['Hours must be a whole number between 1 and '.self::MAX_HOURS.'.'],
            ]);
        }
    }

    private function hasApprovedDtr(Application $application): bool
    {
        return OjtDtrSubmission::query()
            ->where('application_id', $application->id)
            ->where('status', 'approved')
            ->exists();
    }

    private function recordEvent(
        ApplicationHoursAgreement $agreement,
        string $action,
        ?string $from,
        ?string $to,
        ?int $proposed,
        ?int $agreed,
        ?User $actor,
        ?string $side,
        ?string $note,
    ): void {
        ApplicationHoursAgreementEvent::query()->create([
            'agreement_id' => $agreement->id,
            'application_id' => $agreement->application_id,
            'action' => $action,
            'from_status' => $from,
            'to_status' => $to,
            'proposed_hours' => $proposed,
            'agreed_hours' => $agreed,
            'changed_by_user_id' => $actor?->id,
            'changed_by_side' => $side,
            'changed_by_role' => $actor?->effectiveAppRole(),
            'note' => $note,
            'created_at' => now(),
        ]);
    }

    private function notifyParties(Application $application, string $title, string $body, string $type): void
    {
        $application->loadMissing(['student.user', 'student.school', 'internship.company']);
        $recipients = collect();

        if ($application->student?->user_id) {
            $recipients->push((int) $application->student->user_id);
        }
        if ($application->student?->school?->user_id) {
            $recipients->push((int) $application->student->school->user_id);
        }
        if ($application->internship?->company?->user_id) {
            $recipients->push((int) $application->internship->company->user_id);
        }

        foreach ($recipients->unique()->filter() as $userId) {
            Notification::query()->create([
                'user_id' => $userId,
                'title' => $title,
                'body' => $body,
                'channel' => 'in_app',
                'metadata' => [
                    'type' => $type,
                    'applicationId' => (string) $application->id,
                    'redirectTo' => '/applications',
                ],
            ]);
        }
    }

    private function notifyOtherSide(Application $application, string $actorSide, string $title, string $body, string $type): void
    {
        $application->loadMissing(['student.school', 'internship.company', 'student.user']);
        $userId = null;
        if ($actorSide === ApplicationHoursAgreement::SIDE_SCHOOL) {
            $userId = $application->internship?->company?->user_id;
        } else {
            $userId = $application->student?->school?->user_id;
        }
        // Always notify student of visibility updates.
        $targets = collect([(int) $userId, (int) ($application->student?->user_id)])->filter()->unique();
        foreach ($targets as $id) {
            Notification::query()->create([
                'user_id' => $id,
                'title' => $title,
                'body' => $body,
                'channel' => 'in_app',
                'metadata' => [
                    'type' => $type,
                    'applicationId' => (string) $application->id,
                    'redirectTo' => '/applications',
                ],
            ]);
        }
    }
}
