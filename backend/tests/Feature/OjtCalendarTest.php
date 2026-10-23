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
use App\Models\PublicHoliday;
use App\Models\Role;
use App\Models\School;
use App\Models\Student;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OjtCalendarTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_calendar_auth_and_hours_not_double_counted(): void
    {
        $this->assertCalendarForHost('company');
    }

    public function test_municipal_hall_calendar_auth_and_recompute_after_schedule_approval(): void
    {
        $this->assertCalendarForHost('municipal_hall');
    }

    private function assertCalendarForHost(string $hostOrgType): void
    {
        $ctx = $this->createContext($hostOrgType);
        $other = $this->createOtherHost($hostOrgType === 'municipal_hall' ? 'company' : 'municipal_hall');

        PublicHoliday::query()->create([
            'date' => now('Asia/Manila')->addDays(10)->toDateString(),
            'name' => 'Test Holiday',
            'type' => 'national',
        ]);

        Sanctum::actingAs($ctx['hostUser']);
        $job = $this->postJson('/api/internships', [
            'title' => 'Calendar Role',
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
        ])->assertCreated();
        $internshipId = (int) $job->json('data.id');

        Sanctum::actingAs($ctx['studentUser']);
        $applicationId = (int) $this->postApplication($internshipId)->assertCreated()->json('data.id');

        Application::query()->whereKey($applicationId)->update(['required_ojt_hours' => 40]);

        $this->advanceToAccepted($ctx, $applicationId);

        // Student can view own calendar
        Sanctum::actingAs($ctx['studentUser']);
        $cal = $this->getJson('/api/applications/'.$applicationId.'/calendar');
        $cal->assertOk();
        $this->assertSame(40, $cal->json('data.summary.requiredHours'));
        $this->assertSame(0, $cal->json('data.summary.approvedHours'));
        $this->assertNotNull($cal->json('data.summary.ojtStartDate'));
        $this->assertNotEmpty($cal->json('data.days'));

        // Host can view
        Sanctum::actingAs($ctx['hostUser']);
        $this->getJson('/api/applications/'.$applicationId.'/calendar')->assertOk();

        // School can view own student
        Sanctum::actingAs($ctx['schoolUser']);
        $this->getJson('/api/applications/'.$applicationId.'/calendar')->assertOk();

        // Cross-org host 403
        Sanctum::actingAs($other['hostUser']);
        $this->getJson('/api/applications/'.$applicationId.'/calendar')->assertForbidden();

        // Unrelated school 403
        Sanctum::actingAs($other['schoolUser']);
        $this->getJson('/api/applications/'.$applicationId.'/calendar')->assertForbidden();

        // Edge: accepted placement with no schedule row
        $otherJob = Internship::query()->create([
            'company_id' => $ctx['company']->id,
            'title' => 'Bare Calendar Role',
            'description' => 'x',
            'status' => 'active',
            'host_type' => 'company',
        ]);
        $bare = Application::query()->create([
            'internship_id' => $otherJob->id,
            'student_id' => $ctx['student']->id,
            'company_id' => $ctx['company']->id,
            'status' => 'accepted',
            'tracker_stage' => 'accepted',
            'required_ojt_hours' => 40,
            'internship_title' => 'Bare',
            'student_name' => $ctx['studentUser']->name,
            'student_email' => $ctx['studentUser']->email,
        ]);
        Sanctum::actingAs($ctx['studentUser']);
        $noSched = $this->getJson('/api/applications/'.$bare->id.'/calendar')->assertOk();
        $this->assertContains('no_schedule', $noSched->json('data.summary.issues'));

        // Approve DTR hours and ensure sum is from approved rows only (not double)
        Sanctum::actingAs($ctx['studentUser']);
        $dtr = $this->post('/api/ojt-dtrs', [
            'application_id' => $applicationId,
            'period_start' => now()->toDateString(),
            'period_end' => now()->addDays(4)->toDateString(),
            'submitted_hours' => 8,
            'file' => UploadedFile::fake()->create('week.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json']);
        $dtr->assertCreated();
        $dtrId = (int) $dtr->json('data.id');

        // Force approved for hour counting (bypass full DTR chain in this auth-focused test)
        OjtDtrSubmission::query()->whereKey($dtrId)->update([
            'status' => OjtDtrSubmission::STATUS_APPROVED,
        ]);
        // Second approved row would add; replace previous with revision path creates new row — only approved count
        OjtDtrSubmission::query()->create([
            'application_id' => $applicationId,
            'student_id' => $ctx['student']->id,
            'company_id' => $ctx['company']->id,
            'submitted_by_user_id' => $ctx['studentUser']->id,
            'previous_submission_id' => $dtrId,
            'period_start' => now()->toDateString(),
            'period_end' => now()->addDays(4)->toDateString(),
            'submitted_hours' => 8,
            'status' => OjtDtrSubmission::STATUS_REVISION_REQUIRED,
            'file_path' => 'x.pdf',
            'file_name' => 'x.pdf',
        ]);

        Sanctum::actingAs($ctx['hostUser']);
        $after = $this->getJson('/api/applications/'.$applicationId.'/calendar')->assertOk();
        $this->assertSame(8.0, (float) $after->json('data.summary.approvedHours'));
        $this->assertSame(32.0, (float) $after->json('data.summary.remainingHours'));

        // Recompute after schedule approval: request shorter days
        Sanctum::actingAs($ctx['studentUser']);
        $req = $this->postJson('/api/applications/'.$applicationId.'/schedule/request', [
            'slots' => [
                ['day' => 'tue', 'start' => '08:00', 'end' => '12:00'],
                ['day' => 'thu', 'start' => '08:00', 'end' => '12:00'],
            ],
            'reason' => 'Irregular load',
        ])->assertCreated();
        $requestId = (int) $req->json('data.request.id');

        Sanctum::actingAs($ctx['hostUser']);
        $this->postJson('/api/applications/'.$applicationId.'/schedule/requests/'.$requestId.'/decide', [
            'decision' => 'approved',
        ])->assertOk();

        $this->assertTrue(
            PlacementScheduleVersion::query()->where('application_id', $applicationId)->count() >= 2
        );

        $recomputed = $this->getJson('/api/applications/'.$applicationId.'/calendar')->assertOk();
        $this->assertSame('approved', PlacementSchedule::query()->where('application_id', $applicationId)->value('status'));
        $this->assertNotNull($recomputed->json('data.summary.projectedEndDate'));

        // School cannot set start date on company placement
        Sanctum::actingAs($ctx['schoolUser']);
        $this->patchJson('/api/applications/'.$applicationId.'/calendar/start-date', [
            'ojt_start_date' => now('Asia/Manila')->addDay()->toDateString(),
        ])->assertForbidden();

        // Host cannot change start date once an approved DTR exists
        Sanctum::actingAs($ctx['hostUser']);
        $this->patchJson('/api/applications/'.$applicationId.'/calendar/start-date', [
            'ojt_start_date' => now('Asia/Manila')->addDays(2)->toDateString(),
        ])->assertStatus(422);

        // With no approved DTR, host can set start date
        OjtDtrSubmission::query()->where('application_id', $applicationId)->delete();
        PlacementSchedule::query()->where('application_id', $applicationId)->update([
            'ojt_start_locked_at' => null,
        ]);
        $this->patchJson('/api/applications/'.$applicationId.'/calendar/start-date', [
            'ojt_start_date' => now('Asia/Manila')->addDays(2)->toDateString(),
        ])->assertOk();
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function advanceToAccepted(array $ctx, int $applicationId): void
    {
        Sanctum::actingAs($ctx['schoolUser']);
        $this->postJson('/api/applications/'.$applicationId.'/tracker/transition', [
            'to_stage' => 'endorsed',
        ])->assertOk();

        Sanctum::actingAs($ctx['hostUser']);
        $interview = $this->postJson('/api/interviews', [
            'application_id' => $applicationId,
            'scheduled_at' => now()->addDay()->toIso8601String(),
            'mode' => 'online',
        ])->assertCreated()->json('data.id');

        Sanctum::actingAs($ctx['studentUser']);
        $this->patchJson('/api/interviews/'.$interview.'/confirm')->assertOk();

        Sanctum::actingAs($ctx['hostUser']);
        $this->postJson('/api/applications/'.$applicationId.'/tracker/transition', [
            'to_stage' => 'interviewed',
        ])->assertOk();
        $this->postJson('/api/applications/'.$applicationId.'/tracker/assessment', [
            'technical' => 4,
            'communication' => 4,
            'attitude' => 4,
        ])->assertCreated();
        $this->postJson('/api/applications/'.$applicationId.'/tracker/transition', [
            'to_stage' => 'accepted',
        ])->assertOk();
    }

    /**
     * @return array<string, mixed>
     */
    private function createContext(string $hostOrgType): array
    {
        foreach ([
            'org.manage_internships',
            'org.approve_applications',
            'org.manage_applications',
            'org.view_applications',
            'org.manage_assessments',
            'org.approve_ojt_logs',
            'org.submit_ojt_logs',
        ] as $key) {
            Permission::query()->updateOrCreate(
                ['key' => $key],
                ['scope' => 'organization', 'description' => $key]
            );
        }

        $schoolUser = User::factory()->create(['role' => 'school', 'is_active' => true]);
        $schoolSub = Subscription::query()->create(['plan' => 'standard', 'status' => 'active', 'billing_cycle' => 'monthly']);
        $schoolOrg = Organization::query()->create([
            'name' => 'Cal School',
            'type' => 'school',
            'subscription_id' => $schoolSub->id,
            'owner_user_id' => $schoolUser->id,
            'settings' => [],
            'is_active' => true,
        ]);
        $schoolRole = $this->makeRole($schoolOrg, 'school', [
            'org.approve_applications', 'org.manage_applications', 'org.view_applications',
            'org.manage_assessments', 'org.approve_ojt_logs',
        ]);
        OrganizationMembership::query()->create([
            'organization_id' => $schoolOrg->id,
            'user_id' => $schoolUser->id,
            'role_id' => $schoolRole->id,
            'status' => 'active',
            'title' => 'Admin',
        ]);
        $schoolUser->forceFill(['tenant_id' => $schoolOrg->id, 'role_id' => $schoolRole->id])->save();
        $school = School::query()->create([
            'user_id' => $schoolUser->id,
            'organization_id' => $schoolOrg->id,
            'institution_name' => 'Cal School',
            'subscription_code' => 'CAL-'.strtoupper(substr(uniqid(), -4)),
            'verification_status' => 'approved',
        ]);

        $hostUser = User::factory()->create([
            'role' => $hostOrgType === 'municipal_hall' ? 'municipal_hall' : 'company',
            'is_active' => true,
        ]);
        $hostSub = Subscription::query()->create(['plan' => 'standard', 'status' => 'active', 'billing_cycle' => 'monthly']);
        $hostOrg = Organization::query()->create([
            'name' => 'Cal Host '.$hostOrgType,
            'type' => $hostOrgType,
            'subscription_id' => $hostSub->id,
            'owner_user_id' => $hostUser->id,
            'settings' => [],
            'is_active' => true,
        ]);
        $hostRole = $this->makeRole($hostOrg, $hostOrgType, [
            'org.manage_internships', 'org.approve_applications', 'org.manage_applications',
            'org.view_applications', 'org.manage_assessments', 'org.approve_ojt_logs',
        ]);
        OrganizationMembership::query()->create([
            'organization_id' => $hostOrg->id,
            'user_id' => $hostUser->id,
            'role_id' => $hostRole->id,
            'status' => 'active',
            'title' => 'Admin',
        ]);
        $hostUser->forceFill(['tenant_id' => $hostOrg->id, 'role_id' => $hostRole->id])->save();
        $company = Company::query()->create([
            'user_id' => $hostUser->id,
            'organization_id' => $hostOrg->id,
            'company_name' => 'Cal Host Co',
            'company_type' => $hostOrgType === 'municipal_hall' ? 'municipal_hall' : 'private',
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
            'user_id' => $studentUser->id,
            'school_id' => $school->id,
            'school_name' => $school->institution_name,
            'school_subscription_code' => $school->subscription_code,
            'course' => 'BSIT',
        ]);

        return compact('schoolUser', 'school', 'hostUser', 'company', 'studentUser', 'student');
    }

    /**
     * @return array{hostUser: User, schoolUser: User}
     */
    private function createOtherHost(string $hostOrgType): array
    {
        $schoolUser = User::factory()->create(['role' => 'school', 'is_active' => true]);
        $schoolSub = Subscription::query()->create(['plan' => 'standard', 'status' => 'active', 'billing_cycle' => 'monthly']);
        $schoolOrg = Organization::query()->create([
            'name' => 'Other Cal School',
            'type' => 'school',
            'subscription_id' => $schoolSub->id,
            'owner_user_id' => $schoolUser->id,
            'settings' => [],
            'is_active' => true,
        ]);
        $schoolRole = $this->makeRole($schoolOrg, 'school', ['org.approve_applications', 'org.view_applications']);
        OrganizationMembership::query()->create([
            'organization_id' => $schoolOrg->id,
            'user_id' => $schoolUser->id,
            'role_id' => $schoolRole->id,
            'status' => 'active',
            'title' => 'Admin',
        ]);
        $schoolUser->forceFill(['tenant_id' => $schoolOrg->id, 'role_id' => $schoolRole->id])->save();
        School::query()->create([
            'user_id' => $schoolUser->id,
            'organization_id' => $schoolOrg->id,
            'institution_name' => 'Other Cal School',
            'subscription_code' => 'OTH-'.strtoupper(substr(uniqid(), -4)),
            'verification_status' => 'approved',
        ]);

        $hostUser = User::factory()->create([
            'role' => $hostOrgType === 'municipal_hall' ? 'municipal_hall' : 'company',
            'is_active' => true,
        ]);
        $hostSub = Subscription::query()->create(['plan' => 'standard', 'status' => 'active', 'billing_cycle' => 'monthly']);
        $hostOrg = Organization::query()->create([
            'name' => 'Other Cal Host',
            'type' => $hostOrgType,
            'subscription_id' => $hostSub->id,
            'owner_user_id' => $hostUser->id,
            'settings' => [],
            'is_active' => true,
        ]);
        $hostRole = $this->makeRole($hostOrg, $hostOrgType, [
            'org.approve_applications', 'org.manage_applications', 'org.view_applications',
        ]);
        OrganizationMembership::query()->create([
            'organization_id' => $hostOrg->id,
            'user_id' => $hostUser->id,
            'role_id' => $hostRole->id,
            'status' => 'active',
            'title' => 'Admin',
        ]);
        $hostUser->forceFill(['tenant_id' => $hostOrg->id, 'role_id' => $hostRole->id])->save();
        Company::query()->create([
            'user_id' => $hostUser->id,
            'organization_id' => $hostOrg->id,
            'company_name' => 'Other Cal Co',
            'company_type' => $hostOrgType === 'municipal_hall' ? 'municipal_hall' : 'private',
            'verification_status' => 'approved',
        ]);

        return compact('hostUser', 'schoolUser');
    }

    private function makeRole(Organization $org, string $orgType, array $keys): Role
    {
        $role = Role::query()->create([
            'tenant_id' => $org->id,
            'name' => 'Admin '.$orgType,
            'slug' => 'admin_'.$orgType.'_'.uniqid(),
            'scope' => 'organization',
            'organization_type' => $orgType,
        ]);
        $role->permissions()->sync(Permission::query()->whereIn('key', $keys)->pluck('id'));

        return $role;
    }
}
