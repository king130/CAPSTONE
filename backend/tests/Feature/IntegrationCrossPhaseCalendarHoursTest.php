<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Internship;
use App\Models\OjtDtrSubmission;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Permission;
use App\Models\PlacementSchedule;
use App\Models\PlacementScheduleVersion;
use App\Models\Role;
use App\Models\School;
use App\Models\SchoolCourseRequirement;
use App\Models\Student;
use App\Models\Subscription;
use App\Models\User;
use App\Services\ApplicationTracker;
use App\Services\OjtCalendarService;
use App\Support\StudentYearLevel;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * D+C and F+C cross-phase coverage for Company and Municipal Hall.
 */
class IntegrationCrossPhaseCalendarHoursTest extends TestCase
{
    use RefreshDatabase;

    public static function hostTypes(): array
    {
        return [
            'company' => ['company'],
            'municipal hall' => ['municipal_hall'],
        ];
    }

    /**
     * D+C: agreed hours update calendar requiredHours; approved DTR submitted_hours never rewritten.
     *
     * @dataProvider hostTypes
     */
    public function test_agreed_hours_change_calendar_and_do_not_rewrite_approved_dtr(string $hostType): void
    {
        $ctx = $this->createAcceptedPlacement($hostType, schoolHours: 486, hostHours: 300, requiredAfterAgree: 400);

        Sanctum::actingAs($ctx['studentUser']);
        $before = $this->getJson('/api/applications/'.$ctx['applicationId'].'/calendar')->assertOk();
        $this->assertSame(400, (int) $before->json('data.summary.requiredHours'));

        $dtr = OjtDtrSubmission::query()->create([
            'application_id' => $ctx['applicationId'],
            'student_id' => $ctx['student']->id,
            'company_id' => $ctx['company']->id,
            'period_start' => now()->subWeek()->toDateString(),
            'period_end' => now()->toDateString(),
            'submitted_hours' => 8,
            'status' => OjtDtrSubmission::STATUS_APPROVED,
            'submitted_by_user_id' => $ctx['studentUser']->id,
            'file_path' => 'dtr/fixed.pdf',
            'file_name' => 'fixed.pdf',
            'file_mime' => 'application/pdf',
            'file_size' => 100,
        ]);

        Sanctum::actingAs($ctx['schoolUser']);
        $this->postJson('/api/applications/'.$ctx['applicationId'].'/hours-agreement/propose', [
            'hours' => 450,
            'reason' => 'Post-DTR program extension for calendar check',
        ])->assertOk();

        Sanctum::actingAs($ctx['hostUser']);
        $this->postJson('/api/applications/'.$ctx['applicationId'].'/hours-agreement/confirm')->assertOk();

        $this->assertSame(8.0, (float) $dtr->fresh()->submitted_hours);
        $this->assertSame(OjtDtrSubmission::STATUS_APPROVED, $dtr->fresh()->status);

        Sanctum::actingAs($ctx['studentUser']);
        $after = $this->getJson('/api/applications/'.$ctx['applicationId'].'/calendar')->assertOk();
        $this->assertSame(450, (int) $after->json('data.summary.requiredHours'));
        $this->assertSame(8.0, (float) $after->json('data.summary.approvedHours'));
    }

    /**
     * F+C: approved schedule change applies from approval date forward; prior approved hours unchanged.
     *
     * @dataProvider hostTypes
     */
    public function test_approved_schedule_change_applies_from_approval_date_forward(string $hostType): void
    {
        $ctx = $this->createAcceptedPlacement($hostType, schoolHours: 40, hostHours: null, requiredAfterAgree: 40);

        Sanctum::actingAs($ctx['studentUser']);
        $cal1 = $this->getJson('/api/applications/'.$ctx['applicationId'].'/calendar')->assertOk();
        $endBefore = $cal1->json('data.summary.projectedEndDate');

        OjtDtrSubmission::query()->create([
            'application_id' => $ctx['applicationId'],
            'student_id' => $ctx['student']->id,
            'company_id' => $ctx['company']->id,
            'period_start' => now()->subDays(3)->toDateString(),
            'period_end' => now()->subDay()->toDateString(),
            'submitted_hours' => 8,
            'status' => OjtDtrSubmission::STATUS_APPROVED,
            'submitted_by_user_id' => $ctx['studentUser']->id,
            'file_path' => 'dtr/a.pdf',
            'file_name' => 'a.pdf',
            'file_mime' => 'application/pdf',
            'file_size' => 50,
        ]);

        $versionsBefore = PlacementScheduleVersion::query()
            ->where('application_id', $ctx['applicationId'])
            ->count();

        Sanctum::actingAs($ctx['studentUser']);
        $req = $this->postJson('/api/applications/'.$ctx['applicationId'].'/schedule/request', [
            'slots' => [
                ['day' => 'tue', 'start' => '08:00', 'end' => '12:00'],
                ['day' => 'thu', 'start' => '08:00', 'end' => '12:00'],
            ],
            'reason' => 'Shorter weekly load after first week',
        ])->assertCreated();
        $requestId = (int) $req->json('data.request.id');

        $approvalDate = Carbon::now(OjtCalendarService::TIMEZONE)->toDateString();

        Sanctum::actingAs($ctx['hostUser']);
        $this->postJson('/api/applications/'.$ctx['applicationId'].'/schedule/requests/'.$requestId.'/decide', [
            'decision' => 'approved',
        ])->assertOk();

        $this->assertGreaterThan(
            $versionsBefore,
            PlacementScheduleVersion::query()->where('application_id', $ctx['applicationId'])->count()
        );

        $latest = PlacementScheduleVersion::query()
            ->where('application_id', $ctx['applicationId'])
            ->orderByDesc('id')
            ->first();
        $this->assertNotNull($latest);
        $this->assertSame(
            $approvalDate,
            $latest->effective_from instanceof Carbon
                ? $latest->effective_from->toDateString()
                : (string) $latest->effective_from
        );
        $this->assertSame('approved', PlacementSchedule::query()
            ->where('application_id', $ctx['applicationId'])
            ->value('status'));

        Sanctum::actingAs($ctx['studentUser']);
        $cal2 = $this->getJson('/api/applications/'.$ctx['applicationId'].'/calendar')->assertOk();
        $this->assertSame(8.0, (float) $cal2->json('data.summary.approvedHours'));
        $endAfter = $cal2->json('data.summary.projectedEndDate');
        $this->assertNotNull($endAfter);
        $this->assertNotSame($endBefore, $endAfter);
    }

    /**
     * @return array{
     *   hostUser: User,
     *   schoolUser: User,
     *   studentUser: User,
     *   student: Student,
     *   company: Company,
     *   applicationId: int
     * }
     */
    private function createAcceptedPlacement(
        string $hostType,
        int $schoolHours,
        ?int $hostHours,
        int $requiredAfterAgree
    ): array {
        foreach ([
            'org.manage_internships', 'org.approve_applications', 'org.manage_applications',
            'org.approve_ojt_logs', 'org.manage_students', 'org.view_applications',
            'org.manage_assessments',
        ] as $key) {
            Permission::query()->updateOrCreate(['key' => $key], ['scope' => 'organization', 'description' => $key]);
        }

        $schoolUser = User::factory()->create(['role' => 'school', 'is_active' => true]);
        $schoolSub = Subscription::query()->create([
            'plan' => 'standard', 'status' => 'active', 'billing_cycle' => 'monthly',
            'ends_at' => now()->addYear()->toDateString(),
        ]);
        $schoolOrg = Organization::query()->create([
            'name' => 'Cal School', 'type' => 'school', 'subscription_id' => $schoolSub->id,
            'owner_user_id' => $schoolUser->id, 'is_active' => true,
        ]);
        $this->attach($schoolUser, $schoolOrg, 'school');
        $school = School::query()->create([
            'user_id' => $schoolUser->id, 'organization_id' => $schoolOrg->id,
            'institution_name' => 'Cal School', 'subscription_code' => 'CAL-'.uniqid(),
            'verification_status' => 'approved',
        ]);
        SchoolCourseRequirement::query()->create([
            'school_id' => $school->id,
            'course_name' => 'BSIT',
            'course_name_key' => SchoolCourseRequirement::courseNameKey('BSIT'),
            'required_hours' => $schoolHours,
            'eligible_year_levels' => StudentYearLevel::DEFAULT_ELIGIBLE,
        ]);

        $hostUser = User::factory()->create([
            'role' => $hostType === 'municipal_hall' ? 'municipal_hall' : 'company',
            'is_active' => true,
        ]);
        $hostSub = Subscription::query()->create([
            'plan' => 'standard', 'status' => 'active', 'billing_cycle' => 'monthly',
            'ends_at' => now()->addYear()->toDateString(),
        ]);
        $hostOrg = Organization::query()->create([
            'name' => 'Cal Host '.$hostType, 'type' => $hostType, 'subscription_id' => $hostSub->id,
            'owner_user_id' => $hostUser->id, 'is_active' => true,
        ]);
        $this->attach($hostUser, $hostOrg, $hostType);
        $company = Company::query()->create([
            'user_id' => $hostUser->id, 'organization_id' => $hostOrg->id,
            'company_name' => $hostOrg->name,
            'company_type' => $hostType === 'municipal_hall' ? 'municipal_hall' : null,
            'verification_status' => 'approved',
        ]);

        Contract::query()->create([
            'school_user_id' => $schoolUser->id,
            'school_name' => $school->institution_name,
            'company_user_id' => $hostUser->id,
            'company_name' => $company->company_name,
            'requested_by_role' => 'school',
            'requested_by_user_id' => $schoolUser->id,
            'partner_user_id' => $hostUser->id,
            'status' => 'active',
            'subject' => 'Cal MOA',
        ]);

        $studentUser = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $student = Student::query()->create([
            'user_id' => $studentUser->id, 'school_id' => $school->id, 'organization_id' => $schoolOrg->id,
            'school_name' => 'Cal School', 'course' => 'BSIT', 'year_level' => '4th Year',
        ]);

        Sanctum::actingAs($hostUser);
        $jobPayload = [
            'title' => 'Cal Placement '.$hostType,
            'description' => 'x',
            'status' => 'active',
            'requires_resume' => false,
            'schedule_slots' => [
                ['day' => 'mon', 'start' => '08:00', 'end' => '17:00'],
                ['day' => 'tue', 'start' => '08:00', 'end' => '17:00'],
                ['day' => 'wed', 'start' => '08:00', 'end' => '17:00'],
                ['day' => 'thu', 'start' => '08:00', 'end' => '17:00'],
                ['day' => 'fri', 'start' => '08:00', 'end' => '17:00'],
            ],
        ];
        if ($hostHours !== null) {
            $jobPayload['host_required_hours'] = $hostHours;
        }
        $internshipId = (int) $this->postJson('/api/internships', $jobPayload)->assertCreated()->json('data.id');

        Sanctum::actingAs($studentUser);
        $applicationId = (int) $this->postApplication($internshipId, [], $studentUser)->assertCreated()->json('data.id');

        if ($hostHours !== null && $hostHours !== $schoolHours) {
            Sanctum::actingAs($schoolUser);
            $this->postJson('/api/applications/'.$applicationId.'/hours-agreement/propose', [
                'hours' => $requiredAfterAgree,
                'source' => 'custom',
            ])->assertOk();
            Sanctum::actingAs($hostUser);
            $this->postJson('/api/applications/'.$applicationId.'/hours-agreement/confirm')->assertOk();
        } else {
            Application::query()->whereKey($applicationId)->update(['required_ojt_hours' => $requiredAfterAgree]);
        }

        Application::query()->whereKey($applicationId)->update([
            'tracker_stage' => ApplicationTracker::STAGE_ASSESSED,
            'status' => 'endorsed',
        ]);
        Sanctum::actingAs($hostUser);
        $this->postJson('/api/applications/'.$applicationId.'/tracker/transition', [
            'to_stage' => ApplicationTracker::STAGE_ACCEPTED,
        ])->assertOk();

        return compact('hostUser', 'schoolUser', 'studentUser', 'student', 'company') + [
            'applicationId' => $applicationId,
        ];
    }

    private function attach(User $user, Organization $org, string $orgType): void
    {
        $role = Role::query()->create([
            'tenant_id' => $org->id,
            'name' => 'Admin',
            'slug' => 'admin_'.uniqid(),
            'scope' => 'organization',
            'organization_type' => $orgType,
            'is_system' => false,
        ]);
        $role->permissions()->sync(Permission::query()->pluck('id'));
        OrganizationMembership::query()->create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);
        $user->forceFill(['tenant_id' => $org->id, 'role_id' => $role->id])->save();
    }
}
