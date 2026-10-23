<?php

namespace App\Services;

use App\Models\Application;
use App\Models\Internship;
use App\Models\PlacementSchedule;
use App\Models\PlacementScheduleRequest;
use App\Models\PlacementScheduleVersion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class PlacementScheduleService
{
    public const MAX_DAILY_HOURS = 12;

    public const DAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    public const DAY_LABELS = [
        'mon' => 'Monday',
        'tue' => 'Tuesday',
        'wed' => 'Wednesday',
        'thu' => 'Thursday',
        'fri' => 'Friday',
        'sat' => 'Saturday',
        'sun' => 'Sunday',
    ];

    /**
     * System fallback when a posting has no schedule at accept time.
     *
     * @return list<array{day: string, start: string, end: string}>
     */
    public function systemDefaultSlots(): array
    {
        return [
            ['day' => 'mon', 'start' => '08:00', 'end' => '17:00'],
            ['day' => 'tue', 'start' => '08:00', 'end' => '17:00'],
            ['day' => 'wed', 'start' => '08:00', 'end' => '17:00'],
            ['day' => 'thu', 'start' => '08:00', 'end' => '17:00'],
            ['day' => 'fri', 'start' => '08:00', 'end' => '17:00'],
        ];
    }

    /**
     * @param  mixed  $slots
     * @return list<array{day: string, start: string, end: string}>
     */
    public function normalizeAndValidateSlots(mixed $slots, bool $allowEmpty = false): array
    {
        if (! is_array($slots)) {
            throw ValidationException::withMessages([
                'slots' => ['Schedule slots must be an array of day/time entries.'],
            ]);
        }

        $normalized = [];
        $seen = [];

        foreach ($slots as $index => $slot) {
            if (! is_array($slot)) {
                throw ValidationException::withMessages([
                    "slots.$index" => ['Each slot must include day, start, and end.'],
                ]);
            }

            $day = $this->normalizeDay($slot['day'] ?? null);
            $start = $this->normalizeTime($slot['start'] ?? null);
            $end = $this->normalizeTime($slot['end'] ?? null);

            if ($day === null || $start === null || $end === null) {
                throw ValidationException::withMessages([
                    "slots.$index" => ['Each selected day needs a valid start and end time.'],
                ]);
            }

            if (isset($seen[$day])) {
                throw ValidationException::withMessages([
                    "slots.$index.day" => ['Duplicate day in schedule.'],
                ]);
            }
            $seen[$day] = true;

            $startMinutes = $this->timeToMinutes($start);
            $endMinutes = $this->timeToMinutes($end);
            if ($endMinutes <= $startMinutes) {
                throw ValidationException::withMessages([
                    "slots.$index.end" => ['End time must be after start time.'],
                ]);
            }

            $hours = ($endMinutes - $startMinutes) / 60;
            if ($hours > self::MAX_DAILY_HOURS) {
                throw ValidationException::withMessages([
                    "slots.$index.end" => ['Daily hours cannot exceed '.self::MAX_DAILY_HOURS.' hours.'],
                ]);
            }

            $normalized[] = [
                'day' => $day,
                'start' => $start,
                'end' => $end,
            ];
        }

        usort($normalized, function (array $a, array $b) {
            return array_search($a['day'], self::DAYS, true) <=> array_search($b['day'], self::DAYS, true);
        });

        if (! $allowEmpty && $normalized === []) {
            throw ValidationException::withMessages([
                'slots' => ['Select at least one day with start and end times.'],
            ]);
        }

        return $normalized;
    }

    /**
     * @return list<array{day: string, start: string, end: string}>
     */
    public function slotsFromInternship(Internship $internship): array
    {
        if (is_array($internship->schedule_slots) && $internship->schedule_slots !== []) {
            return $this->normalizeAndValidateSlots($internship->schedule_slots);
        }

        $days = is_array($internship->schedule_days) ? $internship->schedule_days : [];
        $start = $internship->time_in ? $this->normalizeTime($internship->time_in) : null;
        $end = $internship->time_out ? $this->normalizeTime($internship->time_out) : null;

        if ($days === [] || $start === null || $end === null) {
            return [];
        }

        $slots = [];
        foreach ($days as $day) {
            $normalizedDay = $this->normalizeDay($day);
            if ($normalizedDay === null) {
                continue;
            }
            $slots[] = [
                'day' => $normalizedDay,
                'start' => $start,
                'end' => $end,
            ];
        }

        return $slots === [] ? [] : $this->normalizeAndValidateSlots($slots);
    }

    /**
     * @param  list<array{day: string, start: string, end: string}>  $slots
     * @return array{schedule: string, schedule_days: list<string>, time_in: string, time_out: string, schedule_slots: list<array{day: string, start: string, end: string}>}
     */
    public function legacyFieldsFromSlots(array $slots): array
    {
        $slots = $this->normalizeAndValidateSlots($slots);
        $labels = array_map(fn (array $s) => self::DAY_LABELS[$s['day']], $slots);
        $starts = array_unique(array_column($slots, 'start'));
        $ends = array_unique(array_column($slots, 'end'));
        $timeIn = count($starts) === 1 ? $starts[0] : $slots[0]['start'];
        $timeOut = count($ends) === 1 ? $ends[0] : $slots[0]['end'];
        $timeLabel = $this->formatAmPm($timeIn).' - '.$this->formatAmPm($timeOut);

        return [
            'schedule' => implode(', ', $labels).' · '.$timeLabel,
            'schedule_days' => $labels,
            'time_in' => $timeIn,
            'time_out' => $timeOut,
            'schedule_slots' => $slots,
        ];
    }

    public function ensureOnAccepted(Application $application): PlacementSchedule
    {
        $existing = PlacementSchedule::query()->where('application_id', $application->id)->first();
        if ($existing) {
            return $existing;
        }

        $application->loadMissing('internship');
        $internship = $application->internship;
        $slots = $internship ? $this->slotsFromInternship($internship) : [];
        $source = 'posting_default';
        if ($slots === []) {
            $slots = $this->systemDefaultSlots();
            $source = 'system_default';
        }

        $startDate = app(OjtCalendarService::class)->defaultStartDateAfterAcceptance()->toDateString();

        return DB::transaction(function () use ($application, $slots, $source, $startDate) {
            $schedule = PlacementSchedule::query()->create([
                'application_id' => $application->id,
                'active_slots' => $slots,
                'status' => 'default',
                'source' => $source,
                'ojt_start_date' => $startDate,
            ]);

            PlacementScheduleVersion::query()->create([
                'application_id' => $application->id,
                'slots' => $slots,
                'effective_from' => $startDate,
                'source' => $source,
            ]);

            return $schedule;
        });
    }

    public function requestChange(Application $application, User $student, array $slots, string $reason): PlacementScheduleRequest
    {
        if ($student->effectiveAppRole() !== 'student') {
            throw new AccessDeniedHttpException('Only students can request schedule changes.');
        }

        if (strtolower((string) $application->status) !== 'accepted'
            && $application->tracker_stage !== ApplicationTracker::STAGE_ACCEPTED) {
            throw ValidationException::withMessages([
                'application' => ['Schedule changes are only available after acceptance.'],
            ]);
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => ['A reason is required for a schedule change request.'],
            ]);
        }

        $normalized = $this->normalizeAndValidateSlots($slots);
        $schedule = $this->ensureOnAccepted($application);

        $open = PlacementScheduleRequest::query()
            ->where('application_id', $application->id)
            ->where('status', 'requested')
            ->exists();
        if ($open) {
            throw ValidationException::withMessages([
                'request' => ['You already have an open schedule request. Wait for a host decision.'],
            ]);
        }

        return DB::transaction(function () use ($application, $student, $normalized, $reason, $schedule) {
            $request = PlacementScheduleRequest::query()->create([
                'application_id' => $application->id,
                'requested_by_user_id' => $student->id,
                'requested_slots' => $normalized,
                'reason' => $reason,
                'status' => 'requested',
            ]);

            $schedule->forceFill(['status' => 'requested'])->save();

            return $request->fresh(['requestedBy']);
        });
    }

    public function decide(
        Application $application,
        PlacementScheduleRequest $request,
        User $host,
        string $decision,
        ?string $note = null,
    ): PlacementScheduleRequest {
        $appRole = $host->effectiveAppRole();
        if ($appRole === 'student') {
            throw new AccessDeniedHttpException('Students cannot decide schedule requests.');
        }
        if ($appRole !== 'company' && $appRole !== 'admin' && $appRole !== 'school') {
            throw new AccessDeniedHttpException('Only the host can decide schedule requests.');
        }

        if ((int) $request->application_id !== (int) $application->id) {
            throw ValidationException::withMessages([
                'request' => ['Request does not belong to this application.'],
            ]);
        }

        if ($request->status !== 'requested') {
            throw ValidationException::withMessages([
                'request' => ['Only open requests can be decided.'],
            ]);
        }

        $decision = strtolower(trim($decision));
        if (! in_array($decision, ['approved', 'declined'], true)) {
            throw ValidationException::withMessages([
                'decision' => ['Decision must be approved or declined.'],
            ]);
        }

        return DB::transaction(function () use ($application, $request, $host, $decision, $note, $appRole) {
            $request->forceFill([
                'status' => $decision,
                'decided_by_user_id' => $host->id,
                'decided_by_role' => $appRole,
                'decided_at' => now(),
                'decision_note' => $note !== null && trim($note) !== '' ? trim($note) : null,
            ])->save();

            $schedule = PlacementSchedule::query()->firstOrCreate(
                ['application_id' => $application->id],
                [
                    'active_slots' => $this->systemDefaultSlots(),
                    'status' => 'default',
                    'source' => 'system_default',
                ]
            );

            if ($decision === 'approved') {
                $effectiveFrom = Carbon::now(OjtCalendarService::TIMEZONE)->toDateString();
                $schedule->forceFill([
                    'active_slots' => $request->requested_slots,
                    'status' => 'approved',
                    'source' => 'approved_request',
                ])->save();
                PlacementScheduleVersion::query()->create([
                    'application_id' => $application->id,
                    'slots' => $request->requested_slots,
                    'effective_from' => $effectiveFrom,
                    'source' => 'approved_request',
                ]);
            } else {
                // Declined: keep active (default/approved) schedule; surface declined status.
                $schedule->forceFill([
                    'status' => 'declined',
                ])->save();
            }

            return $request->fresh(['requestedBy', 'decidedBy']);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeForApplication(Application $application): array
    {
        $application->loadMissing(['placementSchedule', 'placementScheduleRequests.requestedBy', 'placementScheduleRequests.decidedBy', 'internship']);
        $schedule = $application->placementSchedule;
        $requests = $application->placementScheduleRequests
            ->sortByDesc(fn (PlacementScheduleRequest $r) => $r->created_at?->timestamp ?? 0)
            ->values();

        $open = $requests->first(fn (PlacementScheduleRequest $r) => $r->status === 'requested');
        $postingSlots = $application->internship
            ? $this->slotsFromInternship($application->internship)
            : [];

        return [
            'applicationId' => (string) $application->id,
            'hasPlacementSchedule' => (bool) $schedule,
            'status' => $schedule?->status,
            'source' => $schedule?->source,
            'activeSlots' => $schedule?->active_slots ?? null,
            'activeLabel' => $schedule ? $this->slotsLabel($schedule->active_slots ?? []) : null,
            'postingSlots' => $postingSlots,
            'postingLabel' => $postingSlots === [] ? null : $this->slotsLabel($postingSlots),
            'postingSet' => $postingSlots !== [],
            'openRequest' => $open ? $this->serializeRequest($open) : null,
            'history' => $requests->map(fn (PlacementScheduleRequest $r) => $this->serializeRequest($r))->all(),
            'maxDailyHours' => self::MAX_DAILY_HOURS,
        ];
    }

    /**
     * @param  list<array{day?: string, start?: string, end?: string}>|null  $slots
     */
    public function slotsLabel(?array $slots): string
    {
        if (! $slots) {
            return 'Not set';
        }
        try {
            $normalized = $this->normalizeAndValidateSlots($slots);
        } catch (ValidationException) {
            return 'Not set';
        }
        $fields = $this->legacyFieldsFromSlots($normalized);

        return $fields['schedule'];
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeRequest(PlacementScheduleRequest $request): array
    {
        return [
            'id' => (string) $request->id,
            'applicationId' => (string) $request->application_id,
            'requestedSlots' => $request->requested_slots,
            'requestedLabel' => $this->slotsLabel($request->requested_slots),
            'reason' => $request->reason,
            'status' => $request->status,
            'requestedByUserId' => $request->requested_by_user_id ? (string) $request->requested_by_user_id : null,
            'requestedByName' => $request->requestedBy?->name,
            'decidedByUserId' => $request->decided_by_user_id ? (string) $request->decided_by_user_id : null,
            'decidedByName' => $request->decidedBy?->name,
            'decidedByRole' => $request->decided_by_role,
            'decidedAt' => $request->decided_at?->toIso8601String(),
            'decisionNote' => $request->decision_note,
            'createdAt' => $request->created_at?->toIso8601String(),
        ];
    }

    public function normalizeDay(mixed $day): ?string
    {
        $raw = strtolower(trim((string) $day));
        $raw = str_replace(['.', ' '], '', $raw);

        return match ($raw) {
            'mon', 'monday' => 'mon',
            'tue', 'tues', 'tuesday' => 'tue',
            'wed', 'wednesday' => 'wed',
            'thu', 'thur', 'thurs', 'thursday' => 'thu',
            'fri', 'friday' => 'fri',
            'sat', 'saturday' => 'sat',
            'sun', 'sunday' => 'sun',
            default => null,
        };
    }

    public function normalizeTime(mixed $time): ?string
    {
        $raw = trim((string) $time);
        if ($raw === '') {
            return null;
        }

        if (preg_match('/^(\d{1,2}):(\d{2})\s*(am|pm)$/i', $raw, $m)) {
            $hour = (int) $m[1];
            $minute = (int) $m[2];
            $ampm = strtolower($m[3]);
            if ($hour < 1 || $hour > 12 || $minute > 59) {
                return null;
            }
            if ($ampm === 'pm' && $hour < 12) {
                $hour += 12;
            }
            if ($ampm === 'am' && $hour === 12) {
                $hour = 0;
            }

            return sprintf('%02d:%02d', $hour, $minute);
        }

        if (preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', $raw, $m)) {
            $hour = (int) $m[1];
            $minute = (int) $m[2];
            if ($hour > 23 || $minute > 59) {
                return null;
            }

            return sprintf('%02d:%02d', $hour, $minute);
        }

        return null;
    }

    private function timeToMinutes(string $time): int
    {
        [$h, $m] = array_map('intval', explode(':', $time));

        return ($h * 60) + $m;
    }

    public function formatAmPm(string $time): string
    {
        [$h, $m] = array_map('intval', explode(':', $time));
        $suffix = $h >= 12 ? 'PM' : 'AM';
        $hour12 = $h % 12;
        if ($hour12 === 0) {
            $hour12 = 12;
        }

        return sprintf('%d:%02d %s', $hour12, $m, $suffix);
    }
}
