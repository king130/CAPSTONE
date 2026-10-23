<?php

namespace Tests\Unit;

use App\Services\OjtCalendarService;
use App\Services\PlacementScheduleService;
use App\Services\RequiredOjtHoursAccessor;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class OjtCalendarServiceTest extends TestCase
{
    private OjtCalendarService $calendar;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calendar = new OjtCalendarService(
            new RequiredOjtHoursAccessor(),
            new PlacementScheduleService(),
        );
    }

    public function test_break_deduction_for_standard_day(): void
    {
        // 8:00-17:00 = 9h raw - 1h break = 8 credited
        $this->assertSame(8.0, $this->calendar->creditedHoursForSlot([
            'start' => '08:00',
            'end' => '17:00',
        ]));
    }

    public function test_no_break_when_slot_five_hours_or_less(): void
    {
        $this->assertSame(5.0, $this->calendar->creditedHoursForSlot([
            'start' => '08:00',
            'end' => '13:00',
        ]));
        $this->assertSame(4.0, $this->calendar->creditedHoursForSlot([
            'start' => '08:00',
            'end' => '12:00',
        ]));
    }

    public function test_weekend_skip_mon_fri_schedule(): void
    {
        $slots = [
            ['day' => 'mon', 'start' => '08:00', 'end' => '17:00'],
            ['day' => 'tue', 'start' => '08:00', 'end' => '17:00'],
            ['day' => 'wed', 'start' => '08:00', 'end' => '17:00'],
            ['day' => 'thu', 'start' => '08:00', 'end' => '17:00'],
            ['day' => 'fri', 'start' => '08:00', 'end' => '17:00'],
        ];
        // Start on a Monday
        $from = Carbon::parse('2026-01-05', OjtCalendarService::TIMEZONE);
        // 16 hours = 2 working days
        $this->assertSame(2, $this->calendar->workingDaysToCoverHours(16, $slots, $from));
    }

    public function test_holiday_skip(): void
    {
        $slots = [
            ['day' => 'mon', 'start' => '08:00', 'end' => '17:00'],
            ['day' => 'tue', 'start' => '08:00', 'end' => '17:00'],
            ['day' => 'wed', 'start' => '08:00', 'end' => '17:00'],
            ['day' => 'thu', 'start' => '08:00', 'end' => '17:00'],
            ['day' => 'fri', 'start' => '08:00', 'end' => '17:00'],
        ];
        $from = Carbon::parse('2026-01-05', OjtCalendarService::TIMEZONE); // Mon
        // Holiday on Tuesday 2026-01-06 → 16h needs Mon + Wed = 2 days still, but spanning past holiday
        $days = $this->calendar->workingDaysToCoverHours(16, $slots, $from, ['2026-01-06']);
        $this->assertSame(2, $days);
    }

    public function test_five_hundred_hours_example(): void
    {
        $slots = [
            ['day' => 'mon', 'start' => '08:00', 'end' => '17:00'],
            ['day' => 'tue', 'start' => '08:00', 'end' => '17:00'],
            ['day' => 'wed', 'start' => '08:00', 'end' => '17:00'],
            ['day' => 'thu', 'start' => '08:00', 'end' => '17:00'],
            ['day' => 'fri', 'start' => '08:00', 'end' => '17:00'],
        ];
        $from = Carbon::parse('2026-01-05', OjtCalendarService::TIMEZONE);
        // 500 / 8 = 62.5 → ceil via accumulation = 63 working days
        $this->assertSame(63, $this->calendar->workingDaysToCoverHours(500, $slots, $from));
    }

    public function test_irregular_few_days_per_week(): void
    {
        $slots = [
            ['day' => 'tue', 'start' => '08:00', 'end' => '12:00'], // 4h, no break
            ['day' => 'thu', 'start' => '08:00', 'end' => '12:00'],
        ];
        $from = Carbon::parse('2026-01-06', OjtCalendarService::TIMEZONE); // Tue
        // 8 hours = 2 working days (Tue + Thu)
        $this->assertSame(2, $this->calendar->workingDaysToCoverHours(8, $slots, $from));
    }

    public function test_completed_zero_remaining(): void
    {
        $slots = [
            ['day' => 'mon', 'start' => '08:00', 'end' => '17:00'],
        ];
        $from = Carbon::parse('2026-01-05', OjtCalendarService::TIMEZONE);
        $this->assertSame(0, $this->calendar->workingDaysToCoverHours(0, $slots, $from));
    }

    public function test_schedule_change_midway_uses_versioned_slots(): void
    {
        $versions = collect([
            (object) [
                'slots' => [
                    ['day' => 'mon', 'start' => '08:00', 'end' => '17:00'],
                    ['day' => 'wed', 'start' => '08:00', 'end' => '17:00'],
                    ['day' => 'fri', 'start' => '08:00', 'end' => '17:00'],
                ],
                'effective_from' => '2026-01-01',
            ],
            (object) [
                'slots' => [
                    ['day' => 'tue', 'start' => '08:00', 'end' => '12:00'],
                ],
                'effective_from' => '2026-01-10',
            ],
        ]);

        $mon = Carbon::parse('2026-01-05', OjtCalendarService::TIMEZONE);
        $tueAfter = Carbon::parse('2026-01-13', OjtCalendarService::TIMEZONE);

        $before = $this->calendar->slotForDate($mon, $versions);
        $after = $this->calendar->slotForDate($tueAfter, $versions);

        $this->assertSame('mon', $before['day'] ?? null);
        $this->assertSame(8.0, $this->calendar->creditedHoursForSlot($before));
        $this->assertSame('tue', $after['day'] ?? null);
        $this->assertSame(4.0, $this->calendar->creditedHoursForSlot($after));
        // Monday after change should no longer be scheduled
        $this->assertNull($this->calendar->slotForDate(Carbon::parse('2026-01-12', OjtCalendarService::TIMEZONE), $versions));
    }
}
