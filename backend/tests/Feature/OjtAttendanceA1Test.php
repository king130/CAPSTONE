<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Company;
use App\Models\Internship;
use App\Models\OjtAttendanceEvent;
use App\Models\OjtDtrSubmission;
use App\Models\OjtLog;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Permission;
use App\Models\PlacementSchedule;
use App\Models\PlacementScheduleVersion;
use App\Models\Role;
use App\Models\School;
use App\Models\Student;
use App\Models\Subscription;
use App\Models\User;
use App\Services\OjtCalendarService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OjtAttendanceA1Test extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{
     *   schoolUser: User,
     *   companyUser: User,
     *   company: Company,
     *   studentUser: User,
     *   student: Student,
     *   application: Application
     * }
     */
    private function createContext(): array
    {
        foreach (['org.approve_ojt_logs', 'org.manage_applications', 'org.approve_applications', 'org.view_ojt_progress'] as $key) {
            Permission::query()->updateOrCreate(
                ['key' => $key],
                ['scope' => 'organization', 'description' => $key]
            );
        }

        $schoolUser = User::factory()->create(['role' => 'school', 'is_active' => true]);
        $schoolSub = Subscription::query()->create(['plan' => 'standard', 'status' => 'active', 'billing_cycle' => 'monthly']);
        $schoolOrg = Organization::query()->create([
            'name' => 'A1 School',
            'type' => 'school',
            'subscription_id' => $schoolSub->id,
            'owner_user_id' => $schoolUser->id,
            'settings' => ['required_ojt_hours' => 40],
            'is_active' => true,
        ]);
        $schoolRole = Role::query()->create([
            'tenant_id' => $schoolOrg->id,
            'name' => 'School Admin',
            'slug' => 'school_admin_'.uniqid(),
            'scope' => 'organization',
            'organization_type' => 'school',
        ]);
        $schoolRole->permissions()->sync(Permission::query()->whereIn('key', ['org.approve_ojt_logs', 'org.view_ojt_progress'])->pluck('id'));
        OrganizationMembership::query()->create([
            'organization_id' => $schoolOrg->id,
            'user_id' => $schoolUser->id,
            'role_id' => $schoolRole->id,
            'status' => 'active',
        ]);
        $schoolUser->forceFill(['tenant_id' => $schoolOrg->id, 'role_id' => $schoolRole->id])->save();
        $school = School::query()->create([
            'user_id' => $schoolUser->id,
            'organization_id' => $schoolOrg->id,
            'institution_name' => 'A1 School',
            'verification_status' => 'approved',
        ]);

        $companyUser = User::factory()->create(['role' => 'company', 'is_active' => true]);
        $companySub = Subscription::query()->create(['plan' => 'standard', 'status' => 'active', 'billing_cycle' => 'monthly']);
        $companyOrg = Organization::query()->create([
            'name' => 'A1 Company',
            'type' => 'company',
            'subscription_id' => $companySub->id,
            'owner_user_id' => $companyUser->id,
            'is_active' => true,
        ]);
        $companyRole = Role::query()->create([
            'tenant_id' => $companyOrg->id,
            'name' => 'Company Admin',
            'slug' => 'company_admin_'.uniqid(),
            'scope' => 'organization',
            'organization_type' => 'company',
        ]);
        $companyRole->permissions()->sync(Permission::query()->whereIn('key', [
            'org.approve_ojt_logs',
            'org.manage_applications',
            'org.approve_applications',
        ])->pluck('id'));
        OrganizationMembership::query()->create([
            'organization_id' => $companyOrg->id,
            'user_id' => $companyUser->id,
            'role_id' => $companyRole->id,
            'status' => 'active',
        ]);
        $companyUser->forceFill(['tenant_id' => $companyOrg->id, 'role_id' => $companyRole->id])->save();
        $company = Company::query()->create([
            'user_id' => $companyUser->id,
            'organization_id' => $companyOrg->id,
            'company_name' => 'A1 Host Co',
            'verification_status' => 'approved',
        ]);

        $studentUser = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $student = Student::query()->create([
            'user_id' => $studentUser->id,
            'school_id' => $school->id,
            'organization_id' => $schoolOrg->id,
            'school_name' => 'A1 School',
        ]);

        $internship = Internship::query()->create([
            'company_id' => $company->id,
            'title' => 'A1 Placement',
            'company_name' => 'A1 Host Co',
            'slots_available' => 5,
            'is_active' => true,
        ]);

        $application = Application::query()->create([
            'student_id' => $student->id,
            'internship_id' => $internship->id,
            'company_id' => $company->id,
            'internship_title' => 'A1 Placement',
            'status' => 'accepted',
            'required_ojt_hours' => 40,
        ]);

        $schedule = PlacementSchedule::query()->create([
            'application_id' => $application->id,
            'status' => 'default',
            'source' => 'system_default',
            'active_slots' => [
                ['day' => 'mon', 'start' => '08:00', 'end' => '17:00'],
                ['day' => 'tue', 'start' => '08:00', 'end' => '17:00'],
                ['day' => 'wed', 'start' => '08:00', 'end' => '17:00'],
                ['day' => 'thu', 'start' => '08:00', 'end' => '17:00'],
                ['day' => 'fri', 'start' => '08:00', 'end' => '17:00'],
            ],
            'ojt_start_date' => '2026-01-05',
        ]);
        PlacementScheduleVersion::query()->create([
            'application_id' => $application->id,
            'slots' => $schedule->active_slots,
            'effective_from' => '2026-01-05',
            'source' => 'system_default',
        ]);

        return compact('schoolUser', 'companyUser', 'company', 'studentUser', 'student', 'application');
    }

    public function test_host_marks_absence_student_cannot_and_school_is_readonly(): void
    {
        $ctx = $this->createContext();

        Sanctum::actingAs($ctx['studentUser']);
        $this->postJson('/api/applications/'.$ctx['application']->id.'/attendance', [
            'date' => '2026-01-06',
            'type' => 'absent_excused',
            'reason' => 'Sick',
        ])->assertForbidden();

        Sanctum::actingAs($ctx['companyUser']);
        $created = $this->postJson('/api/applications/'.$ctx['application']->id.'/attendance', [
            'date' => '2026-01-06',
            'type' => 'absent_excused',
            'reason' => 'Medical appointment',
        ]);
        $created->assertCreated();
        $created->assertJsonPath('data.type', 'absent_excused');
        $created->assertJsonPath('data.date', '2026-01-06');

        Sanctum::actingAs($ctx['schoolUser']);
        $this->getJson('/api/applications/'.$ctx['application']->id.'/attendance')
            ->assertOk()
            ->assertJsonPath('data.0.type', 'absent_excused');
        $this->postJson('/api/applications/'.$ctx['application']->id.'/attendance', [
            'date' => '2026-01-07',
            'type' => 'absent_unexcused',
            'reason' => 'No call',
        ])->assertForbidden();
    }

    public function test_cannot_mark_absent_when_pending_or_approved_log_exists(): void
    {
        $ctx = $this->createContext();
        OjtLog::query()->create([
            'student_id' => $ctx['student']->id,
            'application_id' => $ctx['application']->id,
            'log_date' => '2026-01-06',
            'time_in' => '08:00',
            'time_out' => '12:00',
            'hours' => 4,
            'is_makeup' => false,
            'tasks' => 'Work',
            'status' => 'pending',
        ]);

        Sanctum::actingAs($ctx['companyUser']);
        $this->postJson('/api/applications/'.$ctx['application']->id.'/attendance', [
            'date' => '2026-01-06',
            'type' => 'absent_excused',
            'reason' => 'Leave',
        ])->assertStatus(422)->assertJsonValidationErrors(['date']);
    }

    public function test_cannot_mark_absent_on_approved_dtr_day(): void
    {
        $ctx = $this->createContext();
        OjtDtrSubmission::query()->create([
            'application_id' => $ctx['application']->id,
            'student_id' => $ctx['student']->id,
            'company_id' => $ctx['company']->id,
            'submitted_by_user_id' => $ctx['studentUser']->id,
            'period_start' => '2026-01-05',
            'period_end' => '2026-01-09',
            'submitted_hours' => 40,
            'status' => OjtDtrSubmission::STATUS_APPROVED,
            'file_path' => 'dtrs/test.pdf',
            'file_name' => 'test.pdf',
        ]);

        Sanctum::actingAs($ctx['companyUser']);
        $this->postJson('/api/applications/'.$ctx['application']->id.'/attendance', [
            'date' => '2026-01-07',
            'type' => 'absent_excused',
            'reason' => 'Leave',
        ])->assertStatus(422)->assertJsonValidationErrors(['date']);
    }

    public function test_student_cannot_log_on_absent_day_but_can_after_clear(): void
    {
        $ctx = $this->createContext();
        Sanctum::actingAs($ctx['companyUser']);
        $eventId = $this->postJson('/api/applications/'.$ctx['application']->id.'/attendance', [
            'date' => '2026-01-06',
            'type' => 'absent_unexcused',
            'reason' => 'No show',
        ])->json('data.id');

        Sanctum::actingAs($ctx['studentUser']);
        $this->postJson('/api/ojt-logs', [
            'date' => '2026-01-06',
            'timeIn' => '08:00',
            'timeOut' => '12:00',
            'tasksDone' => 'Should fail',
            'application_id' => $ctx['application']->id,
        ])->assertStatus(422)->assertJsonValidationErrors(['date']);

        // Make-up on another day is allowed.
        $makeup = $this->postJson('/api/ojt-logs', [
            'date' => '2026-01-10',
            'timeIn' => '08:00',
            'timeOut' => '12:00',
            'tasksDone' => 'Saturday make-up',
            'isMakeup' => true,
            'application_id' => $ctx['application']->id,
        ]);
        $makeup->assertCreated();
        $makeup->assertJsonPath('data.isMakeup', true);

        Log::spy();
        Sanctum::actingAs($ctx['companyUser']);
        $this->deleteJson('/api/applications/'.$ctx['application']->id.'/attendance/'.$eventId)
            ->assertOk();
        Log::shouldHaveReceived('info')->withArgs(fn ($message) => $message === 'ojt_attendance_event_cleared');

        Sanctum::actingAs($ctx['studentUser']);
        $this->postJson('/api/ojt-logs', [
            'date' => '2026-01-06',
            'timeIn' => '08:00',
            'timeOut' => '12:00',
            'tasksDone' => 'After clear',
            'application_id' => $ctx['application']->id,
        ])->assertCreated();
    }

    public function test_daily_log_rejects_over_twelve_hours_without_changing_overnight_rule(): void
    {
        $ctx = $this->createContext();
        Sanctum::actingAs($ctx['studentUser']);

        $this->postJson('/api/ojt-logs', [
            'date' => '2026-01-06',
            'timeIn' => '06:00',
            'timeOut' => '19:00', // 13h
            'tasksDone' => 'Too long',
            'application_id' => $ctx['application']->id,
        ])->assertStatus(422)->assertJsonValidationErrors(['timeOut']);

        // Overnight still rejected by after:timeIn (same as before).
        $this->postJson('/api/ojt-logs', [
            'date' => '2026-01-06',
            'timeIn' => '22:00',
            'timeOut' => '06:00',
            'tasksDone' => 'Overnight',
            'application_id' => $ctx['application']->id,
        ])->assertStatus(422)->assertJsonValidationErrors(['timeOut']);

        $ok = $this->postJson('/api/ojt-logs', [
            'date' => '2026-01-06',
            'timeIn' => '08:00',
            'timeOut' => '17:00',
            'tasksDone' => 'Normal day',
            'application_id' => $ctx['application']->id,
        ]);
        $ok->assertCreated();
        $ok->assertJsonPath('data.isMakeup', false);
    }

    public function test_calendar_shows_absence_skips_projection_and_makeup_badge_keeps_kind(): void
    {
        $ctx = $this->createContext();
        Carbon::setTestNow(Carbon::parse('2026-01-05', OjtCalendarService::TIMEZONE));

        Sanctum::actingAs($ctx['companyUser']);
        $this->postJson('/api/applications/'.$ctx['application']->id.'/attendance', [
            'date' => '2026-01-06', // Tuesday planned leave
            'type' => 'absent_excused',
            'reason' => 'Leave',
        ])->assertCreated();

        Sanctum::actingAs($ctx['studentUser']);
        $this->postJson('/api/ojt-logs', [
            'date' => '2026-01-11', // Sunday non-working
            'timeIn' => '08:00',
            'timeOut' => '12:00',
            'tasksDone' => 'Weekend makeup',
            'isMakeup' => true,
            'application_id' => $ctx['application']->id,
        ])->assertCreated();
        $logId = OjtLog::query()->where('is_makeup', true)->value('id');
        Sanctum::actingAs($ctx['companyUser']);
        $this->patchJson('/api/ojt-logs/'.$logId.'/status', ['status' => 'approved'])->assertOk();

        $calendar = $this->getJson('/api/applications/'.$ctx['application']->id.'/calendar?month=2026-01')
            ->assertOk()
            ->json('data');

        $absentDay = collect($calendar['days'])->firstWhere('date', '2026-01-06');
        $this->assertSame('absent_excused', $absentDay['kind']);
        $this->assertNotSame('completed', $absentDay['kind']);

        $sunday = collect($calendar['days'])->firstWhere('date', '2026-01-11');
        $this->assertSame('non_working', $sunday['kind']);
        $this->assertSame(4.0, (float) $sunday['approvedMakeupHours']);

        // Future absence is skipped in projection (remaining still full 40h).
        $this->assertNotNull($calendar['summary']['projectedEndDate']);

        Carbon::setTestNow();
    }

    public function test_perfect_attendance_without_absence_rows_unchanged_shape(): void
    {
        $ctx = $this->createContext();
        Sanctum::actingAs($ctx['studentUser']);
        $this->getJson('/api/applications/'.$ctx['application']->id.'/calendar?month=2026-01')
            ->assertOk()
            ->assertJsonPath('data.legend.absent_excused', 'Excused absence')
            ->assertJsonPath('data.summary.requiredHours', 40);

        $this->assertSame(0, OjtAttendanceEvent::query()->count());
    }
}
