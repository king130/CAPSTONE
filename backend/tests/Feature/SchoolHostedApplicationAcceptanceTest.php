<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Internship;
use App\Models\Notification;
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

class SchoolHostedApplicationAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{
     *   schoolUser: User,
     *   school: School,
     *   schoolOrg: Organization,
     *   companyUser: User,
     *   company: Company,
     *   companyInternship: Internship,
     *   schoolInternship: Internship,
     *   studentUser: User,
     *   student: Student
     * }
     */
    private function createContext(): array
    {
        foreach ([
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

        $schoolUser = User::factory()->create([
            'role' => 'school',
            'email' => 'school.'.uniqid('', true).'@example.com',
            'is_active' => true,
        ]);
        $schoolSub = Subscription::query()->create([
            'plan' => 'standard',
            'status' => 'active',
            'billing_cycle' => 'monthly',
        ]);
        $schoolOrg = Organization::query()->create([
            'name' => 'Host School Org',
            'type' => 'school',
            'subscription_id' => $schoolSub->id,
            'owner_user_id' => $schoolUser->id,
            'settings' => [],
            'is_active' => true,
        ]);
        $schoolRole = Role::query()->create([
            'tenant_id' => $schoolOrg->id,
            'name' => 'School Admin',
            'slug' => 'school_admin_'.uniqid(),
            'scope' => 'organization',
            'organization_type' => 'school',
        ]);
        $schoolRole->permissions()->sync(
            Permission::query()->whereIn('key', [
                'org.approve_applications',
                'org.manage_applications',
                'org.view_applications',
                'org.manage_assessments',
                'org.approve_ojt_logs',
            ])->pluck('id')
        );
        OrganizationMembership::query()->create([
            'organization_id' => $schoolOrg->id,
            'user_id' => $schoolUser->id,
            'role_id' => $schoolRole->id,
            'status' => 'active',
            'title' => 'Admin',
        ]);
        $schoolUser->forceFill([
            'tenant_id' => $schoolOrg->id,
            'role_id' => $schoolRole->id,
        ])->save();
        $school = School::query()->create([
            'user_id' => $schoolUser->id,
            'organization_id' => $schoolOrg->id,
            'institution_name' => 'Host School',
            'subscription_code' => 'HST-'.strtoupper(substr(uniqid(), -4)),
            'verification_status' => 'approved',
        ]);

        $companyUser = User::factory()->create([
            'role' => 'company',
            'email' => 'company.'.uniqid('', true).'@example.com',
            'is_active' => true,
        ]);
        $companySub = Subscription::query()->create([
            'plan' => 'standard',
            'status' => 'active',
            'billing_cycle' => 'monthly',
        ]);
        $companyOrg = Organization::query()->create([
            'name' => 'Partner Company Org',
            'type' => 'company',
            'subscription_id' => $companySub->id,
            'owner_user_id' => $companyUser->id,
            'settings' => [],
            'is_active' => true,
        ]);
        $companyRole = Role::query()->create([
            'tenant_id' => $companyOrg->id,
            'name' => 'Company Admin',
            'slug' => 'company_admin_'.uniqid(),
            'scope' => 'organization',
            'organization_type' => 'company',
        ]);
        $companyRole->permissions()->sync(
            Permission::query()->whereIn('key', [
                'org.approve_applications',
                'org.manage_applications',
                'org.view_applications',
                'org.manage_assessments',
                'org.approve_ojt_logs',
            ])->pluck('id')
        );
        OrganizationMembership::query()->create([
            'organization_id' => $companyOrg->id,
            'user_id' => $companyUser->id,
            'role_id' => $companyRole->id,
            'status' => 'active',
            'title' => 'Admin',
        ]);
        $companyUser->forceFill([
            'tenant_id' => $companyOrg->id,
            'role_id' => $companyRole->id,
        ])->save();
        $company = Company::query()->create([
            'user_id' => $companyUser->id,
            'organization_id' => $companyOrg->id,
            'company_name' => 'Partner Company',
            'verification_status' => 'approved',
        ]);

        Contract::query()->create([
            'school_user_id' => $schoolUser->id,
            'school_name' => $school->institution_name,
            'company_user_id' => $companyUser->id,
            'company_name' => $company->company_name,
            'requested_by_role' => 'school',
            'requested_by_user_id' => $schoolUser->id,
            'partner_user_id' => $companyUser->id,
            'status' => 'active',
            'subject' => 'Active MOA',
        ]);

        $companyInternship = Internship::query()->create([
            'company_id' => $company->id,
            'host_type' => 'company',
            'requires_resume' => false,
            'host_name' => $company->company_name,
            'title' => 'Company Internship',
            'status' => 'active',
        ]);

        $schoolInternship = Internship::query()->create([
            'company_id' => null,
            'school_id' => $school->id,
            'host_type' => 'school',
            'requires_resume' => false,
            'host_name' => $school->institution_name,
            'title' => 'School Internship',
            'status' => 'active',
        ]);

        $studentUser = User::factory()->create([
            'role' => 'student',
            'email' => 'student.'.uniqid('', true).'@example.com',
            'name' => 'Hosted Student',
            'is_active' => true,
        ]);
        $student = Student::query()->create([
            'user_id' => $studentUser->id,
            'school_id' => $school->id,
            'organization_id' => $schoolOrg->id,
            'school_name' => $school->institution_name,
            'school_subscription_code' => $school->subscription_code,
            'course' => 'BSIT',
        ]);

        return compact(
            'schoolUser',
            'school',
            'schoolOrg',
            'companyUser',
            'company',
            'companyInternship',
            'schoolInternship',
            'studentUser',
            'student'
        );
    }

    /**
     * @return array{user: User, school: School}
     */
    private function createUnrelatedSchool(): array
    {
        $user = User::factory()->create([
            'role' => 'school',
            'email' => 'other.school.'.uniqid('', true).'@example.com',
            'is_active' => true,
        ]);
        $sub = Subscription::query()->create([
            'plan' => 'standard',
            'status' => 'active',
            'billing_cycle' => 'monthly',
        ]);
        $org = Organization::query()->create([
            'name' => 'Other School Org',
            'type' => 'school',
            'subscription_id' => $sub->id,
            'owner_user_id' => $user->id,
            'settings' => [],
            'is_active' => true,
        ]);
        $role = Role::query()->create([
            'tenant_id' => $org->id,
            'name' => 'School Admin',
            'slug' => 'school_admin_'.uniqid(),
            'scope' => 'organization',
            'organization_type' => 'school',
        ]);
        $role->permissions()->sync(
            Permission::query()->where('key', 'org.approve_applications')->pluck('id')
        );
        OrganizationMembership::query()->create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
            'status' => 'active',
            'title' => 'Admin',
        ]);
        $user->forceFill([
            'tenant_id' => $org->id,
            'role_id' => $role->id,
        ])->save();
        $school = School::query()->create([
            'user_id' => $user->id,
            'organization_id' => $org->id,
            'institution_name' => 'Other School',
            'subscription_code' => 'OTH-'.strtoupper(substr(uniqid(), -4)),
            'verification_status' => 'approved',
        ]);

        return compact('user', 'school');
    }

    /**
     * Advance endorsed app through interview + assessment so accept/reject is allowed.
     */
    private function advanceHostToAssessed(User $hostUser, User $studentUser, int|string $applicationId): void
    {
        Sanctum::actingAs($hostUser);
        $interview = $this->postJson('/api/interviews', [
            'application_id' => $applicationId,
            'scheduled_at' => now()->addDay()->toIso8601String(),
            'mode' => 'online',
            'location_or_link' => 'Meet link',
        ]);
        $interview->assertCreated();

        Sanctum::actingAs($studentUser);
        $this->patchJson('/api/interviews/'.$interview->json('data.id').'/confirm')->assertOk();

        Sanctum::actingAs($hostUser);
        $this->postJson('/api/applications/'.$applicationId.'/tracker/transition', [
            'to_stage' => 'interviewed',
        ])->assertOk();
        $this->postJson('/api/applications/'.$applicationId.'/tracker/assessment', [
            'technical' => 4,
            'communication' => 4,
            'attitude' => 4,
        ])->assertCreated();
    }

    public function test_company_hosted_school_endorses_then_company_accepts(): void
    {
        $ctx = $this->createContext();

        Sanctum::actingAs($ctx['studentUser']);
        $apply = $this->postApplication($ctx['companyInternship']->id);
        $apply->assertCreated();
        $applicationId = $apply->json('data.id');
        $this->assertNotNull(Application::query()->find($applicationId)?->company_id);

        Sanctum::actingAs($ctx['schoolUser']);
        $this->patchJson('/api/applications/'.$applicationId.'/status', [
            'status' => 'endorsed',
        ])->assertOk();

        $this->advanceHostToAssessed($ctx['companyUser'], $ctx['studentUser'], $applicationId);

        Sanctum::actingAs($ctx['companyUser']);
        $this->patchJson('/api/applications/'.$applicationId.'/status', [
            'status' => 'accepted',
        ])->assertOk();

        $this->assertSame('accepted', Application::query()->find($applicationId)?->status);
        $this->assertSame(1, Notification::query()->where('user_id', $ctx['studentUser']->id)->where('title', 'Application accepted')->count());
    }

    public function test_school_hosted_host_school_accepts_after_endorsement(): void
    {
        $ctx = $this->createContext();

        Sanctum::actingAs($ctx['studentUser']);
        $apply = $this->postApplication($ctx['schoolInternship']->id);
        $apply->assertCreated();
        $applicationId = $apply->json('data.id');
        $application = Application::query()->findOrFail($applicationId);
        $this->assertNull($application->company_id);
        $this->assertSame('school', $apply->json('data.hostType'));

        Sanctum::actingAs($ctx['schoolUser']);
        $this->patchJson('/api/applications/'.$applicationId.'/status', [
            'status' => 'endorsed',
        ])->assertOk();

        $this->advanceHostToAssessed($ctx['schoolUser'], $ctx['studentUser'], $applicationId);

        Sanctum::actingAs($ctx['schoolUser']);
        $accept = $this->patchJson('/api/applications/'.$applicationId.'/status', [
            'status' => 'accepted',
        ]);
        $accept->assertOk();
        $this->assertSame('accepted', Application::query()->find($applicationId)?->status);
        $this->assertNull(Application::query()->find($applicationId)?->company_id);
        $this->assertSame(1, Notification::query()->where('user_id', $ctx['studentUser']->id)->where('title', 'Application accepted')->count());
    }

    public function test_unrelated_school_cannot_accept_school_hosted_application(): void
    {
        $ctx = $this->createContext();
        $other = $this->createUnrelatedSchool();

        $application = Application::query()->create([
            'internship_id' => $ctx['schoolInternship']->id,
            'student_id' => $ctx['student']->id,
            'company_id' => null,
            'status' => 'endorsed',
            'internship_title' => $ctx['schoolInternship']->title,
            'student_name' => $ctx['studentUser']->name,
            'student_email' => $ctx['studentUser']->email,
        ]);

        Sanctum::actingAs($other['user']);
        $this->patchJson('/api/applications/'.$application->id.'/status', [
            'status' => 'accepted',
        ])->assertForbidden();

        $application->refresh();
        $this->assertSame('endorsed', $application->status);
    }

    public function test_company_cannot_accept_school_hosted_application(): void
    {
        $ctx = $this->createContext();

        $application = Application::query()->create([
            'internship_id' => $ctx['schoolInternship']->id,
            'student_id' => $ctx['student']->id,
            'company_id' => null,
            'status' => 'endorsed',
            'internship_title' => $ctx['schoolInternship']->title,
            'student_name' => $ctx['studentUser']->name,
            'student_email' => $ctx['studentUser']->email,
        ]);

        Sanctum::actingAs($ctx['companyUser']);
        $this->patchJson('/api/applications/'.$application->id.'/status', [
            'status' => 'accepted',
        ])->assertForbidden();

        $application->refresh();
        $this->assertSame('endorsed', $application->status);
        $this->assertNull($application->company_id);
    }

    public function test_student_cannot_accept_own_application(): void
    {
        $ctx = $this->createContext();

        $application = Application::query()->create([
            'internship_id' => $ctx['schoolInternship']->id,
            'student_id' => $ctx['student']->id,
            'company_id' => null,
            'status' => 'endorsed',
            'internship_title' => $ctx['schoolInternship']->title,
            'student_name' => $ctx['studentUser']->name,
            'student_email' => $ctx['studentUser']->email,
        ]);

        Sanctum::actingAs($ctx['studentUser']);
        $this->patchJson('/api/applications/'.$application->id.'/status', [
            'status' => 'accepted',
        ])->assertForbidden();

        $application->refresh();
        $this->assertSame('endorsed', $application->status);
    }

    public function test_school_hosted_rejection_after_assessment_remains_valid(): void
    {
        $ctx = $this->createContext();

        $application = Application::query()->create([
            'internship_id' => $ctx['schoolInternship']->id,
            'student_id' => $ctx['student']->id,
            'company_id' => null,
            'status' => 'endorsed',
            'tracker_stage' => 'endorsed',
            'internship_title' => $ctx['schoolInternship']->title,
            'student_name' => $ctx['studentUser']->name,
            'student_email' => $ctx['studentUser']->email,
        ]);

        $this->advanceHostToAssessed($ctx['schoolUser'], $ctx['studentUser'], $application->id);

        Sanctum::actingAs($ctx['schoolUser']);
        $this->patchJson('/api/applications/'.$application->id.'/status', [
            'status' => 'rejected',
            'reason' => 'Not a fit for the program',
        ])->assertOk();

        $application->refresh();
        $this->assertSame('rejected', $application->status);
        $this->assertSame(1, Notification::query()->where('user_id', $ctx['studentUser']->id)->where('title', 'Application rejected')->count());
    }

    public function test_school_cannot_accept_company_hosted_application(): void
    {
        $ctx = $this->createContext();

        $application = Application::query()->create([
            'internship_id' => $ctx['companyInternship']->id,
            'student_id' => $ctx['student']->id,
            'company_id' => $ctx['company']->id,
            'status' => 'endorsed',
            'internship_title' => $ctx['companyInternship']->title,
            'student_name' => $ctx['studentUser']->name,
            'student_email' => $ctx['studentUser']->email,
        ]);

        Sanctum::actingAs($ctx['schoolUser']);
        $this->patchJson('/api/applications/'.$application->id.'/status', [
            'status' => 'accepted',
        ])->assertStatus(422);

        $application->refresh();
        $this->assertSame('endorsed', $application->status);
    }

    public function test_accepted_school_hosted_application_supports_ojt(): void
    {
        $ctx = $this->createContext();

        $application = Application::query()->create([
            'internship_id' => $ctx['schoolInternship']->id,
            'student_id' => $ctx['student']->id,
            'company_id' => null,
            'status' => 'endorsed',
            'tracker_stage' => 'endorsed',
            'internship_title' => $ctx['schoolInternship']->title,
            'student_name' => $ctx['studentUser']->name,
            'student_email' => $ctx['studentUser']->email,
        ]);

        $this->advanceHostToAssessed($ctx['schoolUser'], $ctx['studentUser'], $application->id);

        Sanctum::actingAs($ctx['schoolUser']);
        $this->patchJson('/api/applications/'.$application->id.'/status', [
            'status' => 'accepted',
        ])->assertOk();

        Sanctum::actingAs($ctx['studentUser']);
        $ojt = $this->postJson('/api/ojt-logs', [
            'application_id' => $application->id,
            'date' => now()->toDateString(),
            'timeIn' => '09:00',
            'timeOut' => '13:00',
            'tasksDone' => 'Campus OJT hours',
        ]);
        $ojt->assertCreated();
        $this->assertDatabaseHas('ojt_logs', [
            'application_id' => $application->id,
            'student_id' => $ctx['student']->id,
            'status' => 'pending',
        ]);
        $this->assertNull(Application::query()->find($application->id)?->company_id);
    }
}
