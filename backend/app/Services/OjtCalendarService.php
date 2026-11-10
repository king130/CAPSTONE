<?php

namespace App\Services;

use App\Models\Application;
use App\Models\OjtAttendanceEvent;
use App\Models\OjtDtrSubmission;
use App\Models\OjtLog;
use App\Models\PlacementSchedule;
use App\Models\PlacementScheduleVersion;
use App\Models\PublicHoliday;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class OjtCalendarService
{
    public const TIMEZONE = 'Asia/Manila';

    public const UNPAID_BREAK_HOURS = 1.0;

    public const BREAK_APPLIES_AFTER_HOURS = 5.0;

    public const START_DATE_MAX_PAST_DAYS = 14;

    public function __construct(
        private readonly RequiredOjtHoursAccessor $requiredHours,
        private readonly PlacementScheduleService $schedules,
    ) {
    }

    /**
     * Credited hours for a single day slot (end - start - unpaid break when long).
     *
     * @param  array{start?: string, end?: string}  $slot
     */
    public function creditedHoursForSlot(array $slot): float
    {
        $start = $this->schedules->normalizeTime($slot['start'] ?? null);
        $end = $this->schedules->normalizeTime($slot['end'] ?? null);
        if ($start === null || $end === null) {
            return 0.0;
        }

        [$sh, $sm] = array_map('intval', explode(':', $start));
        [$eh, $em] = array_map('intval', explode(':', $end));
        $minutes = (($eh * 60) + $em) - (($sh * 60) + $sm);
        if ($minutes <= 0) {
            return 0.0;
        }

        $hours = $minutes / 60;
        if ($hours > self::BREAK_APPLIES_AFTER_HOURS) {
            $hours -= self::UNPAID_BREAK_HOURS;
        }

        return max(0.0, round($hours, 2));
    }

    /**
     * @return array<string, mixed>
     */
    public function build(Application $application, ?string $month = null): array
    {
        $tz = self::TIMEZONE;
        $today = Carbon::now($tz)->startOfDay();
        $monthStart = $month
            ? Carbon::createFromFormat('Y-m', $month, $tz)->startOfMonth()
            : $today->copy()->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();

        $application->loadMissing(['placementSchedule', 'internship']);
        $placement = $application->placementSchedule;
        $required = $this->requiredHours->forApplication($application);
        $approvedHours = $this->approvedHours($application);
        $remaining = $required === null ? null : max(0.0, round($required - $approvedHours, 2));

        $versions = PlacementScheduleVersion::query()
            ->where('application_id', $application->id)
            ->orderBy('effective_from')
            ->orderBy('id')
            ->get();

        if ($versions->isEmpty() && $placement && is_array($placement->active_slots) && $placement->active_slots !== []) {
            $versions = collect([
                (object) [
                    'slots' => $placement->active_slots,
                    'effective_from' => $placement->ojt_start_date
                        ?? Carbon::parse($placement->created_at, $tz)->toDateString(),
                ],
            ]);
        }

        $holidays = PublicHoliday::query()
            ->whereBetween('date', [
                $monthStart->copy()->subMonths(1)->toDateString(),
                $monthEnd->copy()->addYear()->toDateString(),
            ])
            ->get()
            ->keyBy(fn (PublicHoliday $h) => $h->date->toDateString());

        $approvedPeriods = OjtDtrSubmission::query()
            ->where('application_id', $application->id)
            ->where('status', OjtDtrSubmission::STATUS_APPROVED)
            ->get(['id', 'period_start', 'period_end', 'submitted_hours']);

        $absences = OjtAttendanceEvent::query()
            ->where('application_id', $application->id)
            ->whereBetween('date', [
                $monthStart->copy()->subMonths(1)->toDateString(),
                $monthEnd->copy()->addYear()->toDateString(),
            ])
            ->get()
            ->keyBy(fn (OjtAttendanceEvent $event) => $event->date->toDateString());

        $approvedMakeupByDate = OjtLog::query()
            ->where('application_id', $application->id)
            ->where('status', 'approved')
            ->where('is_makeup', true)
            ->whereBetween('log_date', [
                $monthStart->toDateString(),
                $monthEnd->toDateString(),
            ])
            ->get(['log_date', 'hours'])
            ->groupBy(fn (OjtLog $log) => $log->log_date?->toDateString());

        $startDate = $placement?->ojt_start_date
            ? Carbon::parse($placement->ojt_start_date->toDateString(), $tz)->startOfDay()
            : null;

        $issues = [];
        if (! $placement || ! is_array($placement->active_slots) || $placement->active_slots === []) {
            $issues[] = 'no_schedule';
        }
        if (! $startDate) {
            $issues[] = 'no_start_date';
        }
        if ($required === null) {
            $issues[] = 'required_hours_not_configured';
        }

        $days = [];
        $cursor = $monthStart->copy();
        while ($cursor->lte($monthEnd)) {
            $dateKey = $cursor->toDateString();
            $holiday = $holidays->get($dateKey);
            $slot = $this->slotForDate($cursor, $versions);
            $scheduledHours = $slot ? $this->creditedHoursForSlot($slot) : 0.0;
            $inApprovedPeriod = $this->dayInApprovedPeriods($cursor, $approvedPeriods);
            $overScheduled = $this->periodOverScheduledForDay($cursor, $approvedPeriods, $versions, $holidays);
            $absence = $absences->get($dateKey);
            $makeupHours = (float) ($approvedMakeupByDate->get($dateKey)?->sum('hours') ?? 0);

            // Absences never count as completed, even if a DTR somehow overlaps.
            $kind = 'non_working';
            if ($absence) {
                $kind = (string) $absence->type;
            } elseif ($holiday) {
                $kind = 'holiday';
            } elseif ($slot && $scheduledHours > 0) {
                if ($startDate && $cursor->lt($startDate)) {
                    $kind = 'before_start';
                } elseif ($inApprovedPeriod) {
                    $kind = 'completed';
                } else {
                    $kind = 'working';
                }
            }

            $days[] = [
                'date' => $dateKey,
                'kind' => $kind,
                'dayKey' => strtolower($cursor->format('D')),
                'scheduledHours' => $scheduledHours,
                'holidayName' => $holiday?->name,
                'inApprovedDtrPeriod' => $inApprovedPeriod,
                'hoursOverScheduled' => $overScheduled,
                'absenceType' => $absence?->type,
                'absenceReason' => $absence?->reason,
                'approvedMakeupHours' => $makeupHours > 0 ? round($makeupHours, 2) : null,
            ];

            $cursor->addDay();
        }

        $projection = $this->projectEndDate(
            $application,
            $versions,
            $holidays,
            $absences,
            $startDate,
            $today,
            $remaining,
            $issues,
        );

        $startEditable = $placement
            && $placement->ojt_start_locked_at === null
            && ! $this->hasApprovedDtr($application);

        return [
            'applicationId' => (string) $application->id,
            'month' => $monthStart->format('Y-m'),
            'timezone' => $tz,
            'summary' => [
                'requiredHours' => $required,
                'approvedHours' => $approvedHours,
                'remainingHours' => $remaining,
                'ojtStartDate' => $startDate?->toDateString(),
                'ojtStartEditable' => $startEditable,
                'projectedEndDate' => $projection['projectedEndDate'],
                'workingDaysInProjection' => $projection['workingDays'],
                'completedWorkingDays' => $this->countCompletedWorkingDays($startDate, $today, $versions, $holidays, $approvedPeriods),
                'dailyBreakHours' => self::UNPAID_BREAK_HOURS,
                'breakAppliesAfterHours' => self::BREAK_APPLIES_AFTER_HOURS,
                'issues' => $issues,
                'scheduleStatus' => $placement?->status,
                'activeScheduleLabel' => $placement
                    ? $this->schedules->slotsLabel($placement->active_slots ?? [])
                    : null,
            ],
            'days' => $days,
            'legend' => [
                'working' => 'Scheduled working day',
                'completed' => 'Day covered by an approved DTR',
                'holiday' => 'Public holiday',
                'non_working' => 'Not in schedule',
                'before_start' => 'Before OJT start date',
                'absent_excused' => 'Excused absence',
                'absent_unexcused' => 'Unexcused absence',
                'projected_end' => 'Projected end date',
            ],
        ];
    }

    /**
     * Working days needed for remaining hours (ceil), skipping non-schedule, holidays, and absences.
     *
     * @param  Collection<int, mixed>  $versions
     * @param  Collection<string, PublicHoliday>  $holidays
     * @param  Collection<string, OjtAttendanceEvent>  $absences
     * @return array{projectedEndDate: ?string, workingDays: ?int}
     */
    public function projectEndDate(
        Application $application,
        Collection $versions,
        Collection $holidays,
        Collection $absences,
        ?CarbonInterface $startDate,
        CarbonInterface $today,
        ?float $remaining,
        array $issues,
    ): array {
        if (in_array('no_schedule', $issues, true) || in_array('no_start_date', $issues, true) || in_array('required_hours_not_configured', $issues, true)) {
            return ['projectedEndDate' => null, 'workingDays' => null];
        }
        if ($remaining === null) {
            return ['projectedEndDate' => null, 'workingDays' => null];
        }
        if ($remaining <= 0) {
            return ['projectedEndDate' => $today->toDateString(), 'workingDays' => 0];
        }

        $cursor = $today->copy()->startOfDay();
        if ($startDate && $cursor->lt($startDate)) {
            $cursor = $startDate->copy()->startOfDay();
        }

        $needed = 0;
        $hoursLeft = $remaining;
        $guard = 0;
        $lastWorking = null;

        while ($hoursLeft > 0 && $guard < 5000) {
            $guard++;
            $dateKey = $cursor->toDateString();
            if (! $holidays->has($dateKey) && ! $absences->has($dateKey)) {
                $slot = $this->slotForDate($cursor, $versions);
                $credit = $slot ? $this->creditedHoursForSlot($slot) : 0.0;
                if ($credit > 0) {
                    $needed++;
                    $hoursLeft = round($hoursLeft - $credit, 2);
                    $lastWorking = $cursor->copy();
                }
            }
            $cursor->addDay();
        }

        return [
            'projectedEndDate' => $lastWorking?->toDateString(),
            'workingDays' => $needed,
        ];
    }

    /**
     * Pure helper used by unit tests: count working days to cover hours from a start date.
     *
     * @param  list<array{day: string, start: string, end: string}>  $slots
     * @param  list<string>  $holidayDates
     */
    public function workingDaysToCoverHours(
        float $hours,
        array $slots,
        CarbonInterface $from,
        array $holidayDates = [],
    ): int {
        if ($hours <= 0) {
            return 0;
        }
        $holidaySet = array_fill_keys($holidayDates, true);
        $versions = collect([(object) ['slots' => $slots, 'effective_from' => $from->toDateString()]]);
        $cursor = $from->copy()->startOfDay();
        $left = $hours;
        $count = 0;
        $guard = 0;
        while ($left > 0 && $guard < 5000) {
            $guard++;
            $key = $cursor->toDateString();
            if (! isset($holidaySet[$key])) {
                $slot = $this->slotForDate($cursor, $versions);
                $credit = $slot ? $this->creditedHoursForSlot($slot) : 0.0;
                if ($credit > 0) {
                    $count++;
                    $left = round($left - $credit, 2);
                }
            }
            $cursor->addDay();
        }

        return $count;
    }

    public function approvedHours(Application $application): float
    {
        return (float) OjtDtrSubmission::query()
            ->where('application_id', $application->id)
            ->where('status', OjtDtrSubmission::STATUS_APPROVED)
            ->sum('submitted_hours');
    }

    public function hasApprovedDtr(Application $application): bool
    {
        return OjtDtrSubmission::query()
            ->where('application_id', $application->id)
            ->where('status', OjtDtrSubmission::STATUS_APPROVED)
            ->exists();
    }

    public function defaultStartDateAfterAcceptance(?CarbonInterface $acceptedAt = null): CarbonInterface
    {
        $day = ($acceptedAt?->copy() ?? Carbon::now(self::TIMEZONE))->timezone(self::TIMEZONE)->startOfDay()->addDay();
        // Next calendar day is fine; host can edit. Prefer next weekday if Sat/Sun.
        while (in_array((int) $day->dayOfWeekIso, [6, 7], true)) {
            $day->addDay();
        }

        return $day;
    }

    /**
     * @param  Collection<int, mixed>  $versions
     * @return array{day: string, start: string, end: string}|null
     */
    public function slotForDate(CarbonInterface $date, Collection $versions): ?array
    {
        if ($versions->isEmpty()) {
            return null;
        }

        $applicable = null;
        foreach ($versions as $version) {
            $from = $version->effective_from instanceof CarbonInterface
                ? $version->effective_from->toDateString()
                : (string) $version->effective_from;
            if ($from <= $date->toDateString()) {
                $applicable = $version;
            }
        }
        if (! $applicable) {
            return null;
        }

        $dayKey = match ((int) $date->dayOfWeekIso) {
            1 => 'mon',
            2 => 'tue',
            3 => 'wed',
            4 => 'thu',
            5 => 'fri',
            6 => 'sat',
            7 => 'sun',
            default => null,
        };
        $slots = is_array($applicable->slots) ? $applicable->slots : [];
        foreach ($slots as $slot) {
            $day = $this->schedules->normalizeDay($slot['day'] ?? null);
            if ($day === $dayKey) {
                return [
                    'day' => $day,
                    'start' => (string) ($slot['start'] ?? ''),
                    'end' => (string) ($slot['end'] ?? ''),
                ];
            }
        }

        return null;
    }

    /**
     * @param  Collection<int, OjtDtrSubmission>  $periods
     */
    private function dayInApprovedPeriods(CarbonInterface $date, Collection $periods): bool
    {
        $key = $date->toDateString();
        foreach ($periods as $period) {
            $start = $period->period_start?->toDateString();
            $end = $period->period_end?->toDateString();
            if ($start && $end && $key >= $start && $key <= $end) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  Collection<int, OjtDtrSubmission>  $periods
     * @param  Collection<int, mixed>  $versions
     * @param  Collection<string, PublicHoliday>  $holidays
     */
    private function periodOverScheduledForDay(
        CarbonInterface $date,
        Collection $periods,
        Collection $versions,
        Collection $holidays,
    ): bool {
        $key = $date->toDateString();
        foreach ($periods as $period) {
            $start = $period->period_start?->copy()->timezone(self::TIMEZONE)->startOfDay();
            $end = $period->period_end?->copy()->timezone(self::TIMEZONE)->startOfDay();
            if (! $start || ! $end || $key < $start->toDateString() || $key > $end->toDateString()) {
                continue;
            }
            $scheduled = 0.0;
            $cursor = $start->copy();
            while ($cursor->lte($end)) {
                $d = $cursor->toDateString();
                if (! $holidays->has($d)) {
                    $slot = $this->slotForDate($cursor, $versions);
                    if ($slot) {
                        $scheduled += $this->creditedHoursForSlot($slot);
                    }
                }
                $cursor->addDay();
            }
            if ($scheduled > 0 && (float) $period->submitted_hours > round($scheduled, 2)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  Collection<int, mixed>  $versions
     * @param  Collection<string, PublicHoliday>  $holidays
     * @param  Collection<int, OjtDtrSubmission>  $approvedPeriods
     */
    private function countCompletedWorkingDays(
        ?CarbonInterface $startDate,
        CarbonInterface $today,
        Collection $versions,
        Collection $holidays,
        Collection $approvedPeriods,
    ): int {
        if (! $startDate) {
            return 0;
        }
        $count = 0;
        $cursor = $startDate->copy()->startOfDay();
        $end = $today->copy()->startOfDay();
        $guard = 0;
        while ($cursor->lte($end) && $guard < 5000) {
            $guard++;
            $key = $cursor->toDateString();
            if (! $holidays->has($key)) {
                $slot = $this->slotForDate($cursor, $versions);
                if ($slot && $this->creditedHoursForSlot($slot) > 0 && $this->dayInApprovedPeriods($cursor, $approvedPeriods)) {
                    $count++;
                }
            }
            $cursor->addDay();
        }

        return $count;
    }
}
