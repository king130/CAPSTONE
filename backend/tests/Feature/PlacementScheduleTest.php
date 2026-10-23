<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Internship;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Permission;
use App\Models\PlacementSchedule;
use App\Models\PlacementScheduleRequest;
use App\Models\Role;
use App\Models\School;
use App\Models\Student;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlacementScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_default_schedule_copied_on_accept_and_request_flow(): void
    {
        $this->assertScheduleFlow('company');
    }

    public function test_municipal_hall_default_schedule_copied_on_accept_and_request_flow(): void
    {
        $this->assertScheduleFlow('municipal_hall');
    }

    private function assertScheduleFlow(string $hostOrgType): void
    {
        $ctx = $this->createContext($hostOrgType);
        $other = $this->createOtherHost($hostOrgType === 'municipal_hall' ? 'company' : 'municipal_hall');

        Sanctum::actingAs($ctx['hostUser']);
        $job = $this->postJson('/api/internships', [
            'title' => 'Schedule Role '.$hostOrgType,
            'description' => 'Schedule test',
            'status' => 'active',
            'requires_resume' => false,
            'schedule_slots' => [
                ['day' => 'mon', 'start' => '09:00', 'end' => '18:00'],
                ['day' => 'wed', 'start' => '09:00', 'end' => '18:00'],
                ['day' => 'fri', 'start' => '09:00', 'end' => '18:00'],
            ],
        ]);
        $job->assertCreated();
        $internshipId = (int) $job->json('data.id');
        $this->assertNotEmpty($job->json('data.scheduleSlots'));

        // Invalid slot: end before start
        $this->postJson('/api/internships', [
            'title' => 'Bad Schedule',
            'description' => 'x',
            'status' => 'draft',
            'schedule_slots' => [
                ['day' => 'mon', 'start' => '17:00', 'end' => '08:00'],
            ],
        ])->assertStatus(422);

        Sanctum::actingAs($ctx['studentUser']);
        $apply = $this->postApplication($internshipId);
        $apply->assertCreated();
        $applicationId = (int) $apply->json('data.id');

        // Advance to accepted via tracker path used in Phase G
        Sanctum::actingAs($ctx['schoolUser']);
        $this->postJson('/api/applications/'.$applicationId.'/tracker/transition', [
            'to_stage' => 'endorsed',
        ])->assertOk();

        Sanctum::actingAs($ctx['hostUser']);
        $interview = $this->postJson('/api/interviews', [
            'application_id' => $applicationId,
            'scheduled_at' => now()->addDay()->toIso8601String(),
            'mode' => 'online',
        ]);
        $interview->assertCreated();
        Sanctum::actingAs($ctx['studentUser']);
        $this->patchJson('/api/interviews/'.$interview->json('data.id').'/confirm')->assertOk();
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

        $this->assertDatabaseHas('placement_schedules', [
            'application_id' => $applicationId,
            'status' => 'default',
            'source' => 'posting_default',
        ]);
        $schedule = PlacementSchedule::query()->where('application_id', $applicationId)->first();
        $this->assertCount(3, $schedule?->active_slots ?? []);

        // Student requests change
        Sanctum::actingAs($ctx['studentUser']);
        $req = $this->postJson('/api/applications/'.$applicationId.'/schedule/request', [
            'slots' => [
                ['day' => 'tue', 'start' => '08:00', 'end' => '12:00'],
                ['day' => 'thu', 'start' => '08:00', 'end' => '12:00'],
            ],
            'reason' => 'Irregular class load',
        ]);
        $req->assertCreated();
        $requestId = (int) $req->json('data.request.id');
        $this->assertSame('requested', PlacementSchedule::query()->where('application_id', $applicationId)->value('status'));

        // Only one open request
        $this->postJson('/api/applications/'.$applicationId.'/schedule/request', [
            'slots' => [
                ['day' => 'mon', 'start' => '08:00', 'end' => '12:00'],
            ],
            'reason' => 'Another try',
        ])->assertStatus(422);

        // Student cannot approve
        $this->postJson('/api/applications/'.$applicationId.'/schedule/requests/'.$requestId.'/decide', [
            'decision' => 'approved',
        ])->assertForbidden();

        // Coordinating school cannot decide company/MH placements
        Sanctum::actingAs($ctx['schoolUser']);
        $this->postJson('/api/applications/'.$applicationId.'/schedule/requests/'.$requestId.'/decide', [
            'decision' => 'approved',
        ])->assertForbidden();

        // Cross-org host cannot decide
        Sanctum::actingAs($other['hostUser']);
        $this->postJson('/api/applications/'.$applicationId.'/schedule/requests/'.$requestId.'/decide', [
            'decision' => 'approved',
        ])->assertForbidden();

        // Host approves
        Sanctum::actingAs($ctx['hostUser']);
        $this->postJson('/api/applications/'.$applicationId.'/schedule/requests/'.$requestId.'/decide', [
            'decision' => 'approved',
            'note' => 'Looks good',
        ])->assertOk();

        $schedule->refresh();
        $this->assertSame('approved', $schedule->status);
        $this->assertSame('approved_request', $schedule->source);
        $this->assertCount(2, $schedule->active_slots);

        // Decline path after new request
        Sanctum::actingAs($ctx['studentUser']);
        $req2 = $this->postJson('/api/applications/'.$applicationId.'/schedule/request', [
            'slots' => [
                ['day' => 'sat', 'start' => '08:00', 'end' => '16:00'],
            ],
            'reason' => 'Weekend preference',
        ]);
        $req2->assertCreated();
        $request2Id = (int) $req2->json('data.request.id');

        Sanctum::actingAs($ctx['hostUser']);
        $this->postJson('/api/applications/'.$applicationId.'/schedule/requests/'.$request2Id.'/decide', [
            'decision' => 'declined',
            'note' => 'Weekends not available',
        ])->assertOk();

        $schedule->refresh();
        $this->assertSame('declined', $schedule->status);
        $this->assertCount(2, $schedule->active_slots); // previous approved schedule kept
        $this->assertSame(2, PlacementScheduleRequest::query()->where('application_id', $applicationId)->count());

        // Over 12 hours rejected
        Sanctum::actingAs($ctx['studentUser']);
        $this->postJson('/api/applications/'.$applicationId.'/schedule/request', [
            'slots' => [
                ['day' => 'mon', 'start' => '06:00', 'end' => '20:00'],
            ],
            'reason' => 'Too long',
        ])->assertStatus(422);
    }

    public function test_accept_without_posting_schedule_uses_system_default(): void
    {
        $ctx = $this->createContext('company');
        Sanctum::actingAs($ctx['hostUser']);
        $job = $this->postJson('/api/internships', [
            'title' => 'No Schedule Posting',
            'description' => 'x',
            'status' => 'active',
            'requires_resume' => false,
        ]);
        $job->assertCreated();
        $internshipId = (int) $job->json('data.id');

        Sanctum::actingAs($ctx['studentUser']);
        $applicationId = (int) $this->postApplication($internshipId)->json('data.id');

        Sanctum::actingAs($ctx['schoolUser']);
        $this->postJson('/api/applications/'.$applicationId.'/tracker/transition', ['to_stage' => 'endorsed'])->assertOk();
        Sanctum::actingAs($ctx['hostUser']);
        $interview = $this->postJson('/api/interviews', [
            'application_id' => $applicationId,
            'scheduled_at' => now()->addDay()->toIso8601String(),
            'mode' => 'online',
        ])->json('data.id');
        Sanctum::actingAs($ctx['studentUser']);
        $this->patchJson('/api/interviews/'.$interview.'/confirm')->assertOk();
        Sanctum::actingAs($ctx['hostUser']);
        $this->postJson('/api/applications/'.$applicationId.'/tracker/transition', ['to_stage' => 'interviewed'])->assertOk();
        $this->postJson('/api/applications/'.$applicationId.'/tracker/assessment', [
            'technical' => 3, 'communication' => 3, 'attitude' => 3,
        ])->assertCreated();
        $this->postJson('/api/applications/'.$applicationId.'/tracker/transition', ['to_stage' => 'accepted'])->assertOk();

        $schedule = PlacementSchedule::query()->where('application_id', $applicationId)->first();
        $this->assertSame('system_default', $schedule?->source);
        $this->assertCount(5, $schedule?->active_slots ?? []);
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
        ] as $key) {
            Permission::query()->updateOrCreate(
                ['key' => $key],
                ['scope' => 'organization', 'description' => $key]
            );
        }

        $schoolUser = User::factory()->create(['role' => 'school', 'is_active' => true]);
        $schoolSub = Subscription::query()->create(['plan' => 'standard', 'status' => 'active', 'billing_cycle' => 'monthly']);
        $schoolOrg = Organization::query()->create([
            'name' => 'Sched School',
            'type' => 'school',
            'subscription_id' => $schoolSub->id,
            'owner_user_id' => $schoolUser->id,
            'settings' => [],
            'is_active' => true,
        ]);
        $schoolRole = $this->makeRole($schoolOrg, 'school', [
            'org.approve_applications', 'org.manage_applications', 'org.view_applications', 'org.manage_assessments',
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
            'institution_name' => 'Sched School',
            'subscription_code' => 'SCH-'.strtoupper(substr(uniqid(), -4)),
            'verification_status' => 'approved',
        ]);

        $hostUser = User::factory()->create([
            'role' => $hostOrgType === 'municipal_hall' ? 'municipal_hall' : 'company',
            'is_active' => true,
        ]);
        $hostSub = Subscription::query()->create(['plan' => 'standard', 'status' => 'active', 'billing_cycle' => 'monthly']);
        $hostOrg = Organization::query()->create([
            'name' => 'Sched Host '.$hostOrgType,
            'type' => $hostOrgType,
            'subscription_id' => $hostSub->id,
            'owner_user_id' => $hostUser->id,
            'settings' => [],
            'is_active' => true,
        ]);
        $hostRole = $this->makeRole($hostOrg, $hostOrgType, [
            'org.manage_internships', 'org.approve_applications', 'org.manage_applications',
            'org.view_applications', 'org.manage_assessments',
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
            'company_name' => 'Sched Host Co',
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
            'subject' => 'Sched MOA',
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
     * @return array{hostUser: User}
     */
    private function createOtherHost(string $hostOrgType): array
    {
        $hostUser = User::factory()->create([
            'role' => $hostOrgType === 'municipal_hall' ? 'municipal_hall' : 'company',
            'is_active' => true,
        ]);
        $hostSub = Subscription::query()->create(['plan' => 'standard', 'status' => 'active', 'billing_cycle' => 'monthly']);
        $hostOrg = Organization::query()->create([
            'name' => 'Other Sched Host',
            'type' => $hostOrgType,
            'subscription_id' => $hostSub->id,
            'owner_user_id' => $hostUser->id,
            'settings' => [],
            'is_active' => true,
        ]);
        $hostRole = $this->makeRole($hostOrg, $hostOrgType, [
            'org.approve_applications', 'org.manage_applications', 'org.view_applications', 'org.manage_assessments',
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
            'company_name' => 'Other Sched Co',
            'company_type' => $hostOrgType === 'municipal_hall' ? 'municipal_hall' : 'private',
            'verification_status' => 'approved',
        ]);

        return compact('hostUser');
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
