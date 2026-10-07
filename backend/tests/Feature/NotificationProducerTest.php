<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Internship;
use App\Models\Notification;
use App\Models\OjtLog;
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

class NotificationProducerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{
     *   schoolUser: User,
     *   school: School,
     *   schoolOrg: Organization,
     *   companyUser: User,
     *   company: Company,
     *   companyOrg: Organization,
     *   internship: Internship,
     *   studentUser: User,
     *   student: Student
     * }
     */
    private function createApplicationContext(): array
    {
        foreach ([
            'org.approve_applications',
            'org.manage_agreements',
            'org.approve_agreements',
            'org.approve_ojt_logs',
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
            'name' => 'Producer School Org',
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
                'org.manage_agreements',
                'org.approve_agreements',
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
            'institution_name' => 'Producer School',
            'subscription_code' => 'PSC-'.strtoupper(substr(uniqid(), -4)),
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
            'name' => 'Producer Company Org',
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
                'org.manage_agreements',
                'org.approve_agreements',
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
            'company_name' => 'Producer Company',
            'verification_status' => 'approved',
        ]);
        $internship = Internship::query()->create([
            'company_id' => $company->id,
            'host_type' => 'company',
            'title' => 'Producer Internship',
            'status' => 'active',
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
            'subject' => 'Producer MOA',
        ]);

        $studentUser = User::factory()->create([
            'role' => 'student',
            'email' => 'student.'.uniqid('', true).'@example.com',
            'name' => 'Producer Student',
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
            'companyOrg',
            'internship',
            'studentUser',
            'student'
        );
    }

    public function test_application_submitted_notifies_school_user(): void
    {
        $ctx = $this->createApplicationContext();
        Sanctum::actingAs($ctx['studentUser']);

        $response = $this->postJson('/api/applications', [
            'internship_id' => $ctx['internship']->id,
        ]);

        $response->assertCreated();
        $this->assertSame(1, Notification::query()->where('user_id', $ctx['schoolUser']->id)->count());
        $this->assertSame(0, Notification::query()->where('user_id', $ctx['companyUser']->id)->count());
        $notification = Notification::query()->where('user_id', $ctx['schoolUser']->id)->first();
        $this->assertSame('application', $notification->metadata['type'] ?? null);
    }

    public function test_endorsement_notifies_company_once(): void
    {
        $ctx = $this->createApplicationContext();
        $application = Application::query()->create([
            'internship_id' => $ctx['internship']->id,
            'student_id' => $ctx['student']->id,
            'company_id' => $ctx['company']->id,
            'status' => 'submitted',
            'internship_title' => 'Producer Internship',
            'student_name' => $ctx['studentUser']->name,
            'student_email' => $ctx['studentUser']->email,
        ]);

        Sanctum::actingAs($ctx['schoolUser']);
        $this->patchJson('/api/applications/'.$application->id.'/status', [
            'status' => 'endorsed',
        ])->assertOk();

        $this->assertSame(1, Notification::query()->where('user_id', $ctx['companyUser']->id)->count());
        $this->assertSame('endorsement', Notification::query()->where('user_id', $ctx['companyUser']->id)->value('metadata')['type'] ?? null);

        $this->patchJson('/api/applications/'.$application->id.'/status', [
            'status' => 'endorsed',
        ])->assertStatus(422);

        $this->assertSame(1, Notification::query()->where('user_id', $ctx['companyUser']->id)->count());
    }

    public function test_school_rejection_notifies_student(): void
    {
        $ctx = $this->createApplicationContext();
        $application = Application::query()->create([
            'internship_id' => $ctx['internship']->id,
            'student_id' => $ctx['student']->id,
            'company_id' => $ctx['company']->id,
            'status' => 'submitted',
            'internship_title' => 'Producer Internship',
            'student_name' => $ctx['studentUser']->name,
            'student_email' => $ctx['studentUser']->email,
        ]);

        Sanctum::actingAs($ctx['schoolUser']);
        $this->patchJson('/api/applications/'.$application->id.'/status', [
            'status' => 'school_rejected',
        ])->assertOk();

        $this->assertSame(1, Notification::query()->where('user_id', $ctx['studentUser']->id)->count());
        $this->assertSame('application', Notification::query()->where('user_id', $ctx['studentUser']->id)->value('metadata')['type'] ?? null);
    }

    public function test_company_acceptance_notifies_student(): void
    {
        $ctx = $this->createApplicationContext();
        $application = Application::query()->create([
            'internship_id' => $ctx['internship']->id,
            'student_id' => $ctx['student']->id,
            'company_id' => $ctx['company']->id,
            'status' => 'endorsed',
            'internship_title' => 'Producer Internship',
            'student_name' => $ctx['studentUser']->name,
            'student_email' => $ctx['studentUser']->email,
        ]);

        Sanctum::actingAs($ctx['companyUser']);
        $this->patchJson('/api/applications/'.$application->id.'/status', [
            'status' => 'accepted',
        ])->assertOk();

        $this->assertSame(1, Notification::query()->where('user_id', $ctx['studentUser']->id)->count());
    }

    public function test_company_rejection_notifies_student(): void
    {
        $ctx = $this->createApplicationContext();
        $application = Application::query()->create([
            'internship_id' => $ctx['internship']->id,
            'student_id' => $ctx['student']->id,
            'company_id' => $ctx['company']->id,
            'status' => 'endorsed',
            'internship_title' => 'Producer Internship',
            'student_name' => $ctx['studentUser']->name,
            'student_email' => $ctx['studentUser']->email,
        ]);

        Sanctum::actingAs($ctx['companyUser']);
        $this->patchJson('/api/applications/'.$application->id.'/status', [
            'status' => 'rejected',
        ])->assertOk();

        $this->assertSame(1, Notification::query()->where('user_id', $ctx['studentUser']->id)->count());
    }

    public function test_new_agreement_notifies_counterparty_and_duplicate_pending_does_not(): void
    {
        $ctx = $this->createApplicationContext();
        Sanctum::actingAs($ctx['schoolUser']);

        $first = $this->postJson('/api/agreements', [
            'requestedByRole' => 'school',
            'subject' => 'MOA Producer Test',
            'companyId' => $ctx['companyUser']->id,
        ]);
        $first->assertCreated();

        $this->assertSame(1, Notification::query()->where('user_id', $ctx['companyUser']->id)->count());
        $this->assertSame(0, Notification::query()->where('user_id', $ctx['schoolUser']->id)->count());

        $second = $this->postJson('/api/agreements', [
            'requestedByRole' => 'school',
            'subject' => 'MOA Producer Duplicate',
            'companyId' => $ctx['companyUser']->id,
        ]);
        $second->assertStatus(422);

        $this->assertSame(1, Notification::query()->where('user_id', $ctx['companyUser']->id)->count());
    }

    public function test_agreement_acceptance_notifies_requester(): void
    {
        $ctx = $this->createApplicationContext();
        $contract = Contract::query()->create([
            'school_user_id' => $ctx['schoolUser']->id,
            'school_name' => 'Producer School',
            'company_user_id' => $ctx['companyUser']->id,
            'company_name' => 'Producer Company',
            'requested_by_role' => 'school',
            'requested_by_user_id' => $ctx['schoolUser']->id,
            'partner_user_id' => $ctx['companyUser']->id,
            'status' => 'pending',
            'subject' => 'Accept Notify MOA',
        ]);

        Sanctum::actingAs($ctx['companyUser']);
        $this->patchJson('/api/agreements/'.$contract->id.'/accept')->assertOk();

        $this->assertSame(1, Notification::query()->where('user_id', $ctx['schoolUser']->id)->count());
        $this->assertSame('system', Notification::query()->where('user_id', $ctx['schoolUser']->id)->value('metadata')['type'] ?? null);
    }

    public function test_ojt_approval_and_rejection_notify_student_once(): void
    {
        $ctx = $this->createApplicationContext();
        $application = Application::query()->create([
            'internship_id' => $ctx['internship']->id,
            'student_id' => $ctx['student']->id,
            'company_id' => $ctx['company']->id,
            'status' => 'accepted',
            'internship_title' => 'Producer Internship',
            'student_name' => $ctx['studentUser']->name,
            'student_email' => $ctx['studentUser']->email,
        ]);

        $approvedLog = OjtLog::query()->create([
            'student_id' => $ctx['student']->id,
            'application_id' => $application->id,
            'internship_id' => $ctx['internship']->id,
            'organization_id' => $ctx['companyOrg']->id,
            'log_date' => now()->toDateString(),
            'time_in' => '09:00',
            'time_out' => '17:00',
            'hours' => 8,
            'tasks' => 'Worked on tasks',
            'status' => 'pending',
        ]);

        Sanctum::actingAs($ctx['companyUser']);
        $this->patchJson('/api/ojt-logs/'.$approvedLog->id.'/status', [
            'status' => 'approved',
        ])->assertOk();
        $this->assertSame(1, Notification::query()->where('user_id', $ctx['studentUser']->id)->where('metadata->type', 'hours')->count());

        $this->patchJson('/api/ojt-logs/'.$approvedLog->id.'/status', [
            'status' => 'approved',
        ])->assertStatus(422);
        $this->assertSame(1, Notification::query()->where('user_id', $ctx['studentUser']->id)->where('metadata->type', 'hours')->count());

        $rejectedLog = OjtLog::query()->create([
            'student_id' => $ctx['student']->id,
            'application_id' => $application->id,
            'internship_id' => $ctx['internship']->id,
            'organization_id' => $ctx['companyOrg']->id,
            'log_date' => now()->subDay()->toDateString(),
            'time_in' => '09:00',
            'time_out' => '12:00',
            'hours' => 3,
            'tasks' => 'Other tasks',
            'status' => 'pending',
        ]);

        $this->patchJson('/api/ojt-logs/'.$rejectedLog->id.'/status', [
            'status' => 'rejected',
            'reason' => 'Incomplete details',
        ])->assertOk();

        $this->assertSame(2, Notification::query()->where('user_id', $ctx['studentUser']->id)->where('metadata->type', 'hours')->count());
    }

    public function test_organization_verification_notifies_owner_once(): void
    {
        $owner = User::factory()->create([
            'role' => 'school',
            'email' => 'pending-school.'.uniqid('', true).'@example.com',
            'is_active' => true,
        ]);
        $subscription = Subscription::query()->create([
            'plan' => 'free',
            'status' => 'active',
            'billing_cycle' => 'monthly',
        ]);
        $organization = Organization::query()->create([
            'name' => 'Pending School Org',
            'type' => 'school',
            'subscription_id' => $subscription->id,
            'owner_user_id' => $owner->id,
            'settings' => [],
            'is_active' => true,
        ]);
        School::query()->create([
            'user_id' => $owner->id,
            'organization_id' => $organization->id,
            'institution_name' => 'Pending School',
            'verification_status' => 'pending',
        ]);

        $admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin.'.uniqid('', true).'@example.com',
            'is_active' => true,
        ]);

        Sanctum::actingAs($admin);
        $this->patchJson('/api/admin/users/'.$owner->id, [
            'verificationStatus' => 'approved',
        ])->assertOk();

        $this->assertSame(1, Notification::query()->where('user_id', $owner->id)->count());
        $this->assertSame('registration', Notification::query()->where('user_id', $owner->id)->value('metadata')['type'] ?? null);

        $this->patchJson('/api/admin/users/'.$owner->id, [
            'verificationStatus' => 'approved',
        ])->assertOk();
        $this->assertSame(1, Notification::query()->where('user_id', $owner->id)->count());

        $this->patchJson('/api/admin/users/'.$owner->id, [
            'verificationStatus' => 'rejected',
            'verificationRejectionReason' => 'Incomplete registration details.',
        ])->assertOk();
        $this->assertSame(2, Notification::query()->where('user_id', $owner->id)->count());
        $this->assertSame(1, Notification::query()
            ->where('user_id', $owner->id)
            ->where('metadata->verificationStatus', 'rejected')
            ->count());
        $this->assertTrue($owner->fresh()->is_active);
    }
}
