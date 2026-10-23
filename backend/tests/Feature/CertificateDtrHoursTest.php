<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\ApplicationHoursAgreement;
use App\Models\Certificate;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Internship;
use App\Models\OjtDtrSubmission;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Permission;
use App\Models\Role;
use App\Models\School;
use App\Models\SchoolCourseRequirement;
use App\Models\Student;
use App\Models\Subscription;
use App\Models\User;
use App\Services\ApplicationTracker;
use App\Services\RequiredOjtHoursAccessor;
use App\Support\StudentYearLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Certificate issue must count approved DTR submitted_hours and read required hours
 * through RequiredOjtHoursAccessor (agreed hours win over the application snapshot).
 */
class CertificateDtrHoursTest extends TestCase
{
    use RefreshDatabase;

    public function test_certificate_uses_approved_dtr_hours_and_required_ojt_hours_accessor(): void
    {
        $ctx = $this->createAcceptedPlacement(schoolHours: 40, hostHours: 80, agreedHours: 40);

        Sanctum::actingAs($ctx['studentUser']);
        $dtr = OjtDtrSubmission::query()->create([
            'application_id' => $ctx['application']->id,
            'student_id' => $ctx['student']->id,
            'company_id' => $ctx['application']->company_id,
            'submitted_by_user_id' => $ctx['studentUser']->id,
            'period_start' => '2026-10-01',
            'period_end' => '2026-10-10',
            'submitted_hours' => 40,
            'status' => OjtDtrSubmission::STATUS_APPROVED,
            'file_path' => 'dtr/cert-qa.pdf',
            'file_name' => 'cert-qa.pdf',
            'company_reviewed_at' => now(),
            'reviewed_at' => now(),
        ]);

        $this->assertSame(40.0, (float) OjtDtrSubmission::query()
            ->where('application_id', $ctx['application']->id)
            ->where('status', OjtDtrSubmission::STATUS_APPROVED)
            ->sum('submitted_hours'));

        $application = $ctx['application']->fresh(['hoursAgreement']);
        $this->assertSame(40, app(RequiredOjtHoursAccessor::class)->forApplication($application));

        Sanctum::actingAs($ctx['schoolUser']);
        $response = $this->postJson('/api/certificates/generate', [
            'application_id' => $application->id,
        ]);
        $response->assertSuccessful();
        $this->assertDatabaseHas('certificates', [
            'application_id' => $application->id,
            'student_id' => $ctx['student']->id,
        ]);

        // Undershoot: with only 10 approved DTR hours, issue must fail.
        Certificate::query()->where('application_id', $application->id)->delete();
        $dtr->forceFill(['submitted_hours' => 10])->save();
        $this->postJson('/api/certificates/generate', [
            'application_id' => $application->id,
        ])->assertStatus(422)
            ->assertJsonFragment(['message' => 'Student needs at least 40 approved OJT hours (currently 10).']);
    }

    /**
     * @return array{
     *   schoolUser: User,
     *   hostUser: User,
     *   studentUser: User,
     *   student: Student,
     *   application: Application
     * }
     */
    private function createAcceptedPlacement(int $schoolHours, int $hostHours, int $agreedHours): array
    {
        foreach ([
            'org.approve_certificates',
            'org.approve_applications',
            'org.manage_internships',
            'org.manage_students',
            'org.view_reports',
        ] as $key) {
            Permission::query()->updateOrCreate(['key' => $key], ['scope' => 'organization', 'description' => $key]);
        }

        $schoolUser = User::factory()->create(['role' => 'school', 'is_active' => true]);
        $schoolSub = Subscription::query()->create(['plan' => 'standard', 'status' => 'active', 'billing_cycle' => 'monthly']);
        $schoolOrg = Organization::query()->create([
            'name' => 'Cert School',
            'type' => 'school',
            'subscription_id' => $schoolSub->id,
            'owner_user_id' => $schoolUser->id,
            'is_active' => true,
        ]);
        $schoolRole = Role::query()->create([
            'tenant_id' => $schoolOrg->id,
            'name' => 'Admin',
            'slug' => 'admin_'.uniqid(),
            'scope' => 'organization',
            'organization_type' => 'school',
            'is_system' => false,
        ]);
        $schoolRole->permissions()->sync(Permission::query()->pluck('id'));
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
            'institution_name' => 'Cert School',
            'subscription_code' => 'CS-'.uniqid(),
            'verification_status' => 'approved',
        ]);
        SchoolCourseRequirement::query()->create([
            'school_id' => $school->id,
            'course_name' => 'QA BSIT',
            'course_name_key' => SchoolCourseRequirement::courseNameKey('QA BSIT'),
            'required_hours' => $schoolHours,
            'eligible_year_levels' => StudentYearLevel::DEFAULT_ELIGIBLE,
        ]);

        $hostUser = User::factory()->create(['role' => 'company', 'is_active' => true]);
        $hostSub = Subscription::query()->create(['plan' => 'standard', 'status' => 'active', 'billing_cycle' => 'monthly']);
        $hostOrg = Organization::query()->create([
            'name' => 'Cert Co',
            'type' => 'company',
            'subscription_id' => $hostSub->id,
            'owner_user_id' => $hostUser->id,
            'is_active' => true,
        ]);
        $hostRole = Role::query()->create([
            'tenant_id' => $hostOrg->id,
            'name' => 'Host Admin',
            'slug' => 'host_'.uniqid(),
            'scope' => 'organization',
            'organization_type' => 'company',
            'is_system' => false,
        ]);
        $hostRole->permissions()->sync(Permission::query()->pluck('id'));
        OrganizationMembership::query()->create([
            'organization_id' => $hostOrg->id,
            'user_id' => $hostUser->id,
            'role_id' => $hostRole->id,
            'status' => 'active',
        ]);
        $hostUser->forceFill(['tenant_id' => $hostOrg->id, 'role_id' => $hostRole->id])->save();
        $company = Company::query()->create([
            'user_id' => $hostUser->id,
            'organization_id' => $hostOrg->id,
            'company_name' => 'Cert Co',
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
            'subject' => 'Cert MOA',
        ]);

        $internship = Internship::query()->create([
            'company_id' => $company->id,
            'company_name' => $company->company_name,
            'host_type' => 'company',
            'host_name' => $company->company_name,
            'title' => 'Cert Role',
            'status' => 'active',
            'approval_status' => 'approved',
            'slots_available' => 2,
            'host_required_hours' => $hostHours,
            'required_documents' => ['Endorsement'],
            'requires_resume' => false,
        ]);

        $studentUser = User::factory()->create(['role' => 'student', 'is_active' => true, 'name' => 'Cert Intern']);
        $student = Student::query()->create([
            'user_id' => $studentUser->id,
            'school_id' => $school->id,
            'organization_id' => $schoolOrg->id,
            'school_name' => $school->institution_name,
            'course' => 'QA BSIT',
            'year_level' => '4th Year',
        ]);

        $application = Application::query()->create([
            'internship_id' => $internship->id,
            'student_id' => $student->id,
            'company_id' => $company->id,
            'status' => 'accepted',
            'tracker_stage' => ApplicationTracker::STAGE_ACCEPTED,
            'internship_title' => $internship->title,
            'student_name' => $studentUser->name,
            'student_email' => $studentUser->email,
            'student_course' => 'QA BSIT',
            'required_ojt_hours' => $schoolHours,
            'documents' => ['https://example.test/endorsement.pdf'],
        ]);

        ApplicationHoursAgreement::query()->create([
            'application_id' => $application->id,
            'school_required_hours' => $schoolHours,
            'host_offered_hours' => $hostHours,
            'proposed_hours' => $agreedHours,
            'agreed_hours' => $agreedHours,
            'status' => ApplicationHoursAgreement::STATUS_AGREED,
            'school_confirmed_at' => now(),
            'host_confirmed_at' => now(),
        ]);
        $application->forceFill(['required_ojt_hours' => $agreedHours])->save();

        return compact('schoolUser', 'hostUser', 'studentUser', 'student', 'application');
    }
}
