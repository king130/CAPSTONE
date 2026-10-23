<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\ApplicationAssessment;
use App\Models\ApplicationInterview;
use App\Models\ApplicationStageEvent;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Internship;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Permission;
use App\Models\Role;
use App\Models\School;
use App\Models\Student;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApplicationTrackerTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_valid_transitions_and_invalid_jumps(): void
    {
        $this->assertTrackerFlowForHostType('company');
    }

    public function test_municipal_hall_valid_transitions_and_invalid_jumps(): void
    {
        $this->assertTrackerFlowForHostType('municipal_hall');
    }

    private function assertTrackerFlowForHostType(string $hostOrgType): void
    {
        $ctx = $this->createContext($hostOrgType);
        $application = $this->makeApplication($ctx, 'submitted', 'applied');
        $other = $this->createOtherHost($hostOrgType === 'municipal_hall' ? 'company' : 'municipal_hall');

        // Invalid: applied -> accepted
        Sanctum::actingAs($ctx['hostUser']);
        $this->postJson('/api/applications/'.$application->id.'/tracker/transition', [
            'to_stage' => 'accepted',
        ])->assertStatus(422);

        // Student cannot change stages
        Sanctum::actingAs($ctx['studentUser']);
        $this->postJson('/api/applications/'.$application->id.'/tracker/transition', [
            'to_stage' => 'endorsed',
        ])->assertForbidden();

        // Cross-org school cannot endorse
        Sanctum::actingAs($other['schoolUser']);
        $this->postJson('/api/applications/'.$application->id.'/tracker/transition', [
            'to_stage' => 'endorsed',
        ])->assertForbidden();

        // School endorses
        Sanctum::actingAs($ctx['schoolUser']);
        $this->postJson('/api/applications/'.$application->id.'/tracker/transition', [
            'to_stage' => 'endorsed',
        ])->assertOk()->assertJsonPath('data.trackerStage', 'endorsed');

        $this->assertDatabaseHas('application_stage_events', [
            'application_id' => $application->id,
            'to_stage' => 'endorsed',
            'changed_by_user_id' => $ctx['schoolUser']->id,
        ]);

        // Assessment blocked before Interviewed
        Sanctum::actingAs($ctx['hostUser']);
        $this->postJson('/api/applications/'.$application->id.'/tracker/assessment', [
            'technical' => 4,
            'communication' => 4,
            'attitude' => 5,
        ])->assertStatus(422);

        // Invalid: endorsed -> accepted
        $this->postJson('/api/applications/'.$application->id.'/tracker/transition', [
            'to_stage' => 'accepted',
        ])->assertStatus(422);

        // Cross-org host cannot schedule
        Sanctum::actingAs($other['hostUser']);
        $this->postJson('/api/interviews', [
            'application_id' => $application->id,
            'scheduled_at' => now()->addDay()->toIso8601String(),
            'mode' => 'online',
        ])->assertForbidden();

        // Host schedules interview
        Sanctum::actingAs($ctx['hostUser']);
        $interview = $this->postJson('/api/interviews', [
            'application_id' => $application->id,
            'scheduled_at' => now()->addDay()->toIso8601String(),
            'duration_minutes' => 45,
            'mode' => 'online',
            'location_or_link' => 'https://meet.example.com/x',
        ]);
        $interview->assertCreated();
        $application->refresh();
        $this->assertSame('interview_scheduled', $application->tracker_stage);

        // Student can confirm, cannot mark interviewed
        Sanctum::actingAs($ctx['studentUser']);
        $this->patchJson('/api/interviews/'.$interview->json('data.id').'/confirm')->assertOk();
        $this->postJson('/api/applications/'.$application->id.'/tracker/transition', [
            'to_stage' => 'interviewed',
        ])->assertForbidden();
        $this->postJson('/api/applications/'.$application->id.'/tracker/assessment', [
            'technical' => 3,
            'communication' => 3,
            'attitude' => 3,
        ])->assertForbidden();

        // Host marks interviewed
        Sanctum::actingAs($ctx['hostUser']);
        $this->postJson('/api/applications/'.$application->id.'/tracker/transition', [
            'to_stage' => 'interviewed',
        ])->assertOk()->assertJsonPath('data.trackerStage', 'interviewed');

        // Assess
        $assess = $this->postJson('/api/applications/'.$application->id.'/tracker/assessment', [
            'technical' => 4,
            'communication' => 5,
            'attitude' => 3,
            'comments' => 'Solid candidate',
        ]);
        $assess->assertCreated();
        $assess->assertJsonPath('data.overallScore', 4);
        $application->refresh();
        $this->assertSame('assessed', $application->tracker_stage);
        $assessmentId = (int) $assess->json('data.id');
        $this->assertNotNull(ApplicationAssessment::query()->find($assessmentId)?->locked_at);

        // Locked: second assessment rejected
        $this->postJson('/api/applications/'.$application->id.'/tracker/assessment', [
            'technical' => 5,
            'communication' => 5,
            'attitude' => 5,
        ])->assertStatus(422);

        // Accept / reject require assessed (already assessed) — reject needs reason
        $this->postJson('/api/applications/'.$application->id.'/tracker/transition', [
            'to_stage' => 'rejected',
        ])->assertStatus(422);

        $this->postJson('/api/applications/'.$application->id.'/tracker/transition', [
            'to_stage' => 'accepted',
        ])->assertOk()->assertJsonPath('data.trackerStage', 'accepted');

        // Rollback requires reason
        $this->postJson('/api/applications/'.$application->id.'/tracker/transition', [
            'to_stage' => 'assessed',
            'rollback' => true,
        ])->assertStatus(422);

        $this->postJson('/api/applications/'.$application->id.'/tracker/transition', [
            'to_stage' => 'assessed',
            'rollback' => true,
            'reason' => 'Need to revisit decision',
        ])->assertOk()->assertJsonPath('data.trackerStage', 'assessed');

        $this->assertTrue(
            ApplicationStageEvent::query()
                ->where('application_id', $application->id)
                ->where('is_rollback', true)
                ->where('reason', 'Need to revisit decision')
                ->exists()
        );

        // History visible on show
        $show = $this->getJson('/api/applications/'.$application->id.'/tracker');
        $show->assertOk();
        $this->assertNotEmpty($show->json('data.history'));
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
        ] as $key) {
            Permission::query()->updateOrCreate(
                ['key' => $key],
                ['scope' => 'organization', 'description' => $key]
            );
        }

        $schoolUser = User::factory()->create(['role' => 'school', 'is_active' => true]);
        $schoolSub = Subscription::query()->create(['plan' => 'standard', 'status' => 'active', 'billing_cycle' => 'monthly']);
        $schoolOrg = Organization::query()->create([
            'name' => 'Tracker School',
            'type' => 'school',
            'subscription_id' => $schoolSub->id,
            'owner_user_id' => $schoolUser->id,
            'settings' => [],
            'is_active' => true,
        ]);
        $schoolRole = $this->makeRole($schoolOrg, 'school', [
            'org.approve_applications',
            'org.manage_applications',
            'org.view_applications',
            'org.manage_assessments',
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
            'institution_name' => 'Tracker School',
            'subscription_code' => 'TRK-'.strtoupper(substr(uniqid(), -4)),
            'verification_status' => 'approved',
        ]);

        $hostUser = User::factory()->create([
            'role' => $hostOrgType === 'municipal_hall' ? 'municipal_hall' : 'company',
            'is_active' => true,
        ]);
        $hostSub = Subscription::query()->create(['plan' => 'standard', 'status' => 'active', 'billing_cycle' => 'monthly']);
        $hostOrg = Organization::query()->create([
            'name' => 'Tracker Host '.$hostOrgType,
            'type' => $hostOrgType,
            'subscription_id' => $hostSub->id,
            'owner_user_id' => $hostUser->id,
            'settings' => [],
            'is_active' => true,
        ]);
        $hostRole = $this->makeRole($hostOrg, $hostOrgType, [
            'org.manage_internships',
            'org.approve_applications',
            'org.manage_applications',
            'org.view_applications',
            'org.manage_assessments',
            'org.approve_ojt_logs',
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
            'company_name' => 'Tracker Host Co',
            'company_type' => $hostOrgType === 'municipal_hall' ? 'municipal_hall' : 'private',
            'verification_status' => 'approved',
        ]);

        $studentUser = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $student = Student::query()->create([
            'user_id' => $studentUser->id,
            'school_id' => $school->id,
            'school_name' => $school->institution_name,
            'school_subscription_code' => $school->subscription_code,
            'course' => 'BSIT',
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
            'subject' => 'Tracker MOA',
        ]);

        $internship = Internship::query()->create([
            'company_id' => $company->id,
            'title' => 'Tracker Role',
            'description' => 'Test',
            'status' => 'active',
            'host_type' => $hostOrgType === 'municipal_hall' ? 'company' : 'company',
            'slots_available' => 2,
        ]);

        return compact('schoolUser', 'school', 'hostUser', 'company', 'studentUser', 'student', 'internship', 'hostOrg');
    }

    /**
     * @return array{hostUser: User, schoolUser: User}
     */
    private function createOtherHost(string $hostOrgType): array
    {
        $schoolUser = User::factory()->create(['role' => 'school', 'is_active' => true]);
        $schoolSub = Subscription::query()->create(['plan' => 'standard', 'status' => 'active', 'billing_cycle' => 'monthly']);
        $schoolOrg = Organization::query()->create([
            'name' => 'Other School',
            'type' => 'school',
            'subscription_id' => $schoolSub->id,
            'owner_user_id' => $schoolUser->id,
            'settings' => [],
            'is_active' => true,
        ]);
        $schoolRole = $this->makeRole($schoolOrg, 'school', ['org.approve_applications', 'org.manage_applications', 'org.view_applications']);
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
            'institution_name' => 'Other School',
            'subscription_code' => 'OTH-'.strtoupper(substr(uniqid(), -4)),
            'verification_status' => 'approved',
        ]);

        $hostUser = User::factory()->create([
            'role' => $hostOrgType === 'municipal_hall' ? 'municipal_hall' : 'company',
            'is_active' => true,
        ]);
        $hostSub = Subscription::query()->create(['plan' => 'standard', 'status' => 'active', 'billing_cycle' => 'monthly']);
        $hostOrg = Organization::query()->create([
            'name' => 'Other Host',
            'type' => $hostOrgType,
            'subscription_id' => $hostSub->id,
            'owner_user_id' => $hostUser->id,
            'settings' => [],
            'is_active' => true,
        ]);
        $hostRole = $this->makeRole($hostOrg, $hostOrgType, [
            'org.approve_applications',
            'org.manage_applications',
            'org.view_applications',
            'org.manage_assessments',
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
            'company_name' => 'Other Host Co',
            'company_type' => $hostOrgType === 'municipal_hall' ? 'municipal_hall' : 'private',
            'verification_status' => 'approved',
        ]);

        return compact('hostUser', 'schoolUser');
    }

    private function makeRole(Organization $org, string $orgType, array $permissionKeys): Role
    {
        $role = Role::query()->create([
            'tenant_id' => $org->id,
            'name' => 'Admin '.$orgType,
            'slug' => 'admin_'.$orgType.'_'.uniqid(),
            'scope' => 'organization',
            'organization_type' => $orgType,
        ]);
        $role->permissions()->sync(
            Permission::query()->whereIn('key', $permissionKeys)->pluck('id')
        );

        return $role;
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function makeApplication(array $ctx, string $status, string $trackerStage): Application
    {
        return Application::query()->create([
            'internship_id' => $ctx['internship']->id,
            'student_id' => $ctx['student']->id,
            'company_id' => $ctx['company']->id,
            'status' => $status,
            'tracker_stage' => $trackerStage,
            'internship_title' => $ctx['internship']->title,
            'student_name' => $ctx['studentUser']->name,
            'student_email' => $ctx['studentUser']->email,
            'student_course' => 'BSIT',
        ]);
    }
}
