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
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CertificateIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_sees_own_certificates_and_empty_list_when_none(): void
    {
        $ctx = $this->createPlacement();

        Sanctum::actingAs($ctx['studentUser']);
        $this->getJson('/api/certificates')
            ->assertOk()
            ->assertJsonPath('data', []);

        $certificate = $this->issueCertificate($ctx);

        $this->getJson('/api/certificates')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', (string) $certificate->id)
            ->assertJsonPath('data.0.studentId', (string) $ctx['student']->id);
    }

    public function test_student_cannot_see_another_students_certificates(): void
    {
        $ctx = $this->createPlacement();
        $certificate = $this->issueCertificate($ctx);

        $otherUser = User::factory()->create(['role' => 'student', 'is_active' => true]);
        Student::query()->create([
            'user_id' => $otherUser->id,
            'school_id' => $ctx['school']->id,
            'organization_id' => $ctx['schoolOrg']->id,
            'school_name' => $ctx['school']->institution_name,
            'course' => 'QA BSIT',
            'year_level' => '4th Year',
        ]);

        Sanctum::actingAs($otherUser);
        $this->getJson('/api/certificates')
            ->assertOk()
            ->assertJsonPath('data', []);

        $this->get('/api/certificates/'.$certificate->id.'/download')->assertForbidden();
    }

    public function test_school_with_view_reports_can_index_but_not_issue_without_approve_permission(): void
    {
        $ctx = $this->createPlacement();
        $this->issueCertificate($ctx);

        $viewer = $this->attachSchoolMember($ctx, ['org.view_reports', 'org.reports_view'], 'Viewer');

        Sanctum::actingAs($viewer);
        $this->getJson('/api/certificates')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/certificates/eligible')->assertForbidden();
        $this->postJson('/api/certificates/generate', [
            'application_id' => $ctx['application']->id,
        ])->assertForbidden();
    }

    public function test_school_without_view_or_approve_permission_gets_403_on_index(): void
    {
        $ctx = $this->createPlacement();
        $this->issueCertificate($ctx);

        $restricted = $this->attachSchoolMember($ctx, ['org.manage_profile'], 'Profile Only');

        Sanctum::actingAs($restricted);
        $this->getJson('/api/certificates')
            ->assertForbidden()
            ->assertJsonPath('message', 'Missing permission to view certificates.');
    }

    public function test_company_index_is_scoped_to_own_company_only(): void
    {
        $ctx = $this->createPlacement();
        $this->issueCertificate($ctx);

        Sanctum::actingAs($ctx['hostUser']);
        $this->getJson('/api/certificates')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $other = $this->createOtherCompany();
        Sanctum::actingAs($other['user']);
        $this->getJson('/api/certificates')
            ->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_placement_ended_applications_remain_eligible_and_indexable(): void
    {
        $ctx = $this->createPlacement(status: 'placement_ended');
        $this->approveHours($ctx, 40);

        Sanctum::actingAs($ctx['schoolUser']);
        $eligible = $this->getJson('/api/certificates/eligible')->assertOk();
        $this->assertTrue(collect($eligible->json('data'))->contains(
            fn (array $row) => (string) $row['applicationId'] === (string) $ctx['application']->id
                && $row['eligible'] === true
        ));

        $this->postJson('/api/certificates/generate', [
            'application_id' => $ctx['application']->id,
        ])->assertCreated();

        Sanctum::actingAs($ctx['studentUser']);
        $this->getJson('/api/certificates')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.applicationId', (string) $ctx['application']->id);
    }

    public function test_accepted_applications_use_required_ojt_hours_accessor_for_eligibility(): void
    {
        $ctx = $this->createPlacement(schoolHours: 40, hostHours: 80, agreedHours: 40, status: 'accepted');
        $this->approveHours($ctx, 40);

        $application = $ctx['application']->fresh(['hoursAgreement']);
        $this->assertSame(40, app(RequiredOjtHoursAccessor::class)->forApplication($application));

        Sanctum::actingAs($ctx['schoolUser']);
        $this->getJson('/api/certificates/eligible')
            ->assertOk()
            ->assertJsonFragment([
                'applicationId' => (string) $application->id,
                'requiredHours' => 40,
                'approvedHours' => 40,
                'eligible' => true,
            ]);
    }

    public function test_ineligible_status_and_insufficient_hours_cannot_receive_certificates(): void
    {
        $ctx = $this->createPlacement(status: 'accepted');
        $ctx['application']->forceFill([
            'status' => 'endorsed',
            'tracker_stage' => ApplicationTracker::STAGE_ENDORSED,
        ])->save();

        Sanctum::actingAs($ctx['schoolUser']);
        $this->postJson('/api/certificates/generate', [
            'application_id' => $ctx['application']->id,
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Certificate requires an accepted or completed placement.');

        $ctx['application']->forceFill([
            'status' => 'accepted',
            'tracker_stage' => ApplicationTracker::STAGE_ACCEPTED,
        ])->save();
        $this->approveHours($ctx, 10);

        $this->postJson('/api/certificates/generate', [
            'application_id' => $ctx['application']->id,
        ])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Student needs at least 40 approved OJT hours (currently 10).']);
    }

    public function test_download_renders_printable_georgia_layout_with_print_controls_hidden_in_print_css(): void
    {
        Storage::fake('local');
        $ctx = $this->createPlacement();
        $certificate = $this->issueCertificate($ctx);

        Sanctum::actingAs($ctx['studentUser']);
        $response = $this->get('/api/certificates/'.$certificate->id.'/download')
            ->assertOk();

        $this->assertStringContainsString('text/html', (string) $response->headers->get('Content-Type'));
        $html = $response->getContent();
        $this->assertIsString($html);
        $this->assertStringContainsString('Georgia', $html);
        $this->assertStringContainsString('#1e3a5f', $html);
        $this->assertStringContainsString('border: 3px double #1e3a5f', $html);
        $this->assertStringContainsString('Print Certificate', $html);
        $this->assertStringContainsString('@media print', $html);
        $this->assertStringContainsString('.toolbar { display: none !important; }', $html);
        $this->assertStringContainsString('Certificate of Completion', $html);
        $this->assertStringContainsString('Cert Index Intern', $html);
        $this->assertStringNotContainsString('MainLayout', $html);
        $this->assertStringNotContainsString('dashboard', $html);
    }

    public function test_company_cannot_download_another_companys_certificate_by_id(): void
    {
        $ctx = $this->createPlacement();
        $certificate = $this->issueCertificate($ctx);
        $other = $this->createOtherCompany();

        Sanctum::actingAs($other['user']);
        $this->get('/api/certificates/'.$certificate->id.'/download')->assertForbidden();
    }

    /**
     * @return array{
     *   schoolUser: User,
     *   school: School,
     *   schoolOrg: Organization,
     *   hostUser: User,
     *   company: Company,
     *   studentUser: User,
     *   student: Student,
     *   application: Application
     * }
     */
    private function createPlacement(
        int $schoolHours = 40,
        int $hostHours = 80,
        int $agreedHours = 40,
        string $status = 'accepted'
    ): array {
        foreach ([
            'org.approve_certificates',
            'org.approve_applications',
            'org.manage_internships',
            'org.manage_students',
            'org.manage_profile',
            'org.view_reports',
            'org.reports_view',
        ] as $key) {
            Permission::query()->updateOrCreate(['key' => $key], ['scope' => 'organization', 'description' => $key]);
        }

        $schoolUser = User::factory()->create(['role' => 'school', 'is_active' => true]);
        $schoolSub = Subscription::query()->create(['plan' => 'standard', 'status' => 'active', 'billing_cycle' => 'monthly']);
        $schoolOrg = Organization::query()->create([
            'name' => 'Cert Index School',
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
            'institution_name' => 'Cert Index School',
            'subscription_code' => 'CIS-'.uniqid(),
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
            'name' => 'Cert Index Co',
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
            'company_name' => 'Cert Index Co',
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
            'subject' => 'Cert Index MOA',
        ]);

        $internship = Internship::query()->create([
            'company_id' => $company->id,
            'company_name' => $company->company_name,
            'host_type' => 'company',
            'host_name' => $company->company_name,
            'title' => 'Cert Index Role',
            'status' => 'active',
            'approval_status' => 'approved',
            'slots_available' => 2,
            'host_required_hours' => $hostHours,
            'required_documents' => ['Endorsement'],
            'requires_resume' => false,
        ]);

        $studentUser = User::factory()->create(['role' => 'student', 'is_active' => true, 'name' => 'Cert Index Intern']);
        $student = Student::query()->create([
            'user_id' => $studentUser->id,
            'school_id' => $school->id,
            'organization_id' => $schoolOrg->id,
            'school_name' => $school->institution_name,
            'course' => 'QA BSIT',
            'year_level' => '4th Year',
        ]);

        $trackerStage = $status === 'placement_ended'
            ? ApplicationTracker::STAGE_PLACEMENT_ENDED
            : ApplicationTracker::STAGE_ACCEPTED;

        $application = Application::query()->create([
            'internship_id' => $internship->id,
            'student_id' => $student->id,
            'company_id' => $company->id,
            'status' => $status,
            'tracker_stage' => $trackerStage,
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

        return compact(
            'schoolUser',
            'school',
            'schoolOrg',
            'hostUser',
            'company',
            'studentUser',
            'student',
            'application'
        );
    }

    private function attachSchoolMember(array $ctx, array $permissionKeys, string $title): User
    {
        $user = User::factory()->create(['role' => 'school', 'is_active' => true]);
        $role = Role::query()->create([
            'tenant_id' => $ctx['schoolOrg']->id,
            'name' => $title,
            'slug' => strtolower(str_replace(' ', '_', $title)).'_'.uniqid(),
            'scope' => 'organization',
            'organization_type' => 'school',
            'is_system' => false,
        ]);
        $role->permissions()->sync(
            Permission::query()->whereIn('key', $permissionKeys)->pluck('id')
        );
        OrganizationMembership::query()->create([
            'organization_id' => $ctx['schoolOrg']->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
            'status' => 'active',
            'title' => $title,
        ]);
        $user->forceFill([
            'tenant_id' => $ctx['schoolOrg']->id,
            'role_id' => $role->id,
        ])->save();

        return $user;
    }

    /**
     * @return array{user: User}
     */
    private function createOtherCompany(): array
    {
        $user = User::factory()->create(['role' => 'company', 'is_active' => true]);
        $sub = Subscription::query()->create(['plan' => 'standard', 'status' => 'active', 'billing_cycle' => 'monthly']);
        $org = Organization::query()->create([
            'name' => 'Other Cert Co',
            'type' => 'company',
            'subscription_id' => $sub->id,
            'owner_user_id' => $user->id,
            'is_active' => true,
        ]);
        $role = Role::query()->create([
            'tenant_id' => $org->id,
            'name' => 'Other Host',
            'slug' => 'other_host_'.uniqid(),
            'scope' => 'organization',
            'organization_type' => 'company',
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
        Company::query()->create([
            'user_id' => $user->id,
            'organization_id' => $org->id,
            'company_name' => 'Other Cert Co',
            'verification_status' => 'approved',
        ]);

        return compact('user');
    }

    private function approveHours(array $ctx, float $hours): void
    {
        OjtDtrSubmission::query()->create([
            'application_id' => $ctx['application']->id,
            'student_id' => $ctx['student']->id,
            'company_id' => $ctx['application']->company_id,
            'submitted_by_user_id' => $ctx['studentUser']->id,
            'period_start' => '2026-10-01',
            'period_end' => '2026-10-10',
            'submitted_hours' => $hours,
            'status' => OjtDtrSubmission::STATUS_APPROVED,
            'file_path' => 'dtr/cert-index.pdf',
            'file_name' => 'cert-index.pdf',
            'company_reviewed_at' => now(),
            'reviewed_at' => now(),
        ]);
    }

    private function issueCertificate(array $ctx): Certificate
    {
        Storage::fake('local');
        $this->approveHours($ctx, 40);

        Sanctum::actingAs($ctx['schoolUser']);
        $response = $this->postJson('/api/certificates/generate', [
            'application_id' => $ctx['application']->id,
        ]);
        $response->assertCreated();

        return Certificate::query()->findOrFail((int) $response->json('data.id'));
    }
}
