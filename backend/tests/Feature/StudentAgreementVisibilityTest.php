<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Contract;
use App\Models\Internship;
use App\Models\Organization;
use App\Models\School;
use App\Models\SchoolCourseRequirement;
use App\Models\Student;
use App\Models\Subscription;
use App\Models\User;
use App\Support\StudentYearLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StudentAgreementVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_agreement_permits_visibility_and_apply(): void
    {
        $ctx = $this->createContext();
        $this->createAgreement($ctx, 'active');
        $internship = $this->createCompanyInternship($ctx);

        Sanctum::actingAs($ctx['studentUser']);

        $this->getJson('/api/internships?status=active')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', (string) $internship->id);

        $this->getJson('/api/internships/'.$internship->id)
            ->assertOk()
            ->assertJsonPath('data.id', (string) $internship->id);

        $this->getJson('/api/organizations?type=company')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', (string) $ctx['companyOrg']->id);

        $this->postApplication($internship->id)->assertCreated();
    }

    public function test_no_agreement_hides_company_postings_and_blocks_detail_and_apply(): void
    {
        $ctx = $this->createContext();
        $internship = $this->createCompanyInternship($ctx);

        Sanctum::actingAs($ctx['studentUser']);

        $this->getJson('/api/internships?status=active')
            ->assertOk()
            ->assertJsonPath('data', []);

        $this->getJson('/api/internships/'.$internship->id)
            ->assertForbidden()
            ->assertJsonPath('code', 'agreement_required');

        $this->getJson('/api/organizations?type=company')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->getJson('/api/organizations/'.$ctx['companyOrg']->id)
            ->assertForbidden()
            ->assertJsonPath('code', 'agreement_required');

        $this->postApplication($internship->id)
            ->assertStatus(422)
            ->assertJsonPath('code', 'agreement_required');
    }

    public function test_existing_application_keeps_detail_access_after_agreement_ends(): void
    {
        $ctx = $this->createContext();
        $this->createAgreement($ctx, 'active');
        $internship = $this->createCompanyInternship($ctx);

        Sanctum::actingAs($ctx['studentUser']);
        $this->postApplication($internship->id)->assertCreated();

        Contract::query()->delete();

        $this->getJson('/api/internships?status=active')
            ->assertOk()
            ->assertJsonPath('data', []);

        $this->getJson('/api/internships/'.$internship->id)
            ->assertOk()
            ->assertJsonPath('data.id', (string) $internship->id);

        $this->postApplication($internship->id)
            ->assertStatus(422)
            ->assertJsonPath('code', 'agreement_required');
    }

    public function test_another_schools_agreement_does_not_grant_access(): void
    {
        $ctx = $this->createContext();
        $other = $this->createOtherSchool();
        $this->createAgreement([
            'schoolUser' => $other['schoolUser'],
            'school' => $other['school'],
            'schoolOrg' => $other['schoolOrg'],
            'companyUser' => $ctx['companyUser'],
            'company' => $ctx['company'],
            'companyOrg' => $ctx['companyOrg'],
        ], 'active');
        $internship = $this->createCompanyInternship($ctx);

        Sanctum::actingAs($ctx['studentUser']);
        $this->getJson('/api/internships?status=active')->assertOk()->assertJsonPath('data', []);
        $this->getJson('/api/internships/'.$internship->id)->assertForbidden();
        $this->postApplication($internship->id)
            ->assertStatus(422)
            ->assertJsonPath('code', 'agreement_required');
    }

    public function test_pending_rejected_cancelled_and_expired_agreements_revoke_access(): void
    {
        $ctx = $this->createContext();
        $internship = $this->createCompanyInternship($ctx);

        Sanctum::actingAs($ctx['studentUser']);

        foreach (['pending', 'rejected', 'cancelled'] as $status) {
            Contract::query()->delete();
            $this->createAgreement($ctx, $status);
            $this->getJson('/api/internships?status=active')->assertOk()->assertJsonPath('data', []);
            $this->getJson('/api/internships/'.$internship->id)->assertForbidden();
        }

        Contract::query()->delete();
        $this->createAgreement($ctx, 'active', [
            'end_date' => now()->subDay()->toDateString(),
        ]);
        $this->getJson('/api/internships?status=active')->assertOk()->assertJsonPath('data', []);
        $this->getJson('/api/internships/'.$internship->id)->assertForbidden();
    }

    public function test_pending_amendment_keeps_access_for_previously_active_partnership(): void
    {
        $ctx = $this->createContext();
        $this->createAgreement($ctx, 'pending_amendment');
        $internship = $this->createCompanyInternship($ctx);

        Sanctum::actingAs($ctx['studentUser']);
        $this->getJson('/api/internships?status=active')
            ->assertOk()
            ->assertJsonCount(1, 'data');
        $this->getJson('/api/internships/'.$internship->id)->assertOk();
    }

    public function test_search_pagination_and_totals_exclude_ineligible_postings(): void
    {
        $ctx = $this->createContext();
        $partnered = $this->createCompanyInternship($ctx, ['title' => 'Partnered Role']);
        $outsider = $this->createOtherCompanyInternship(['title' => 'Outsider Role']);
        $this->createAgreement($ctx, 'active');

        Sanctum::actingAs($ctx['studentUser']);

        $paged = $this->getJson('/api/internships?status=active&page=1&per_page=10&q=Role');
        $paged->assertOk();
        $paged->assertJsonPath('meta.total', 1);
        $this->assertSame([(string) $partnered->id], collect($paged->json('data'))->pluck('id')->all());
        $this->assertNotContains((string) $outsider->id, collect($paged->json('data'))->pluck('id'));
    }

    public function test_student_without_school_association_sees_no_restricted_hosts(): void
    {
        $ctx = $this->createContext();
        $this->createAgreement($ctx, 'active');
        $this->createCompanyInternship($ctx);

        $ctx['student']->forceFill([
            'school_id' => null,
            'organization_id' => null,
            'school_subscription_code' => null,
            'school_name' => null,
        ])->save();

        Sanctum::actingAs($ctx['studentUser']);
        $this->getJson('/api/internships?status=active')->assertOk()->assertJsonPath('data', []);
        $this->getJson('/api/organizations?type=company')->assertOk()->assertJsonPath('meta.total', 0);
    }

    public function test_municipal_hall_requires_same_active_agreement_rules(): void
    {
        $ctx = $this->createContext(hostType: 'municipal_hall');
        $internship = $this->createCompanyInternship($ctx, ['title' => 'LGU Program']);

        Sanctum::actingAs($ctx['studentUser']);
        $this->getJson('/api/internships?status=active')->assertOk()->assertJsonPath('data', []);
        $this->getJson('/api/organizations?type=municipal_hall')->assertOk()->assertJsonPath('meta.total', 0);

        $this->createAgreement($ctx, 'active');
        $this->getJson('/api/internships?status=active')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', (string) $internship->id);
        $this->getJson('/api/organizations?type=municipal_hall')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_own_school_hosted_internship_remains_visible_without_company_agreement(): void
    {
        $ctx = $this->createContext();
        $schoolHosted = Internship::query()->create([
            'school_id' => $ctx['school']->id,
            'host_type' => 'school',
            'host_name' => $ctx['school']->institution_name,
            'title' => 'Campus Lab Intern',
            'status' => 'active',
            'approval_status' => 'approved',
            'slots_available' => 2,
            'requires_resume' => false,
            'required_documents' => ['Endorsement'],
        ]);

        Sanctum::actingAs($ctx['studentUser']);
        $this->getJson('/api/internships?status=active')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', (string) $schoolHosted->id);
    }

    public function test_two_schools_two_companies_isolation_matrix(): void
    {
        $schoolA = $this->createContext();
        $schoolB = $this->createOtherSchoolWithStudent();
        $companyY = $this->createStandaloneCompany('Company Y');

        $this->createAgreement($schoolA, 'active');
        $this->createAgreement([
            'schoolUser' => $schoolB['schoolUser'],
            'school' => $schoolB['school'],
            'schoolOrg' => $schoolB['schoolOrg'],
            'companyUser' => $companyY['companyUser'],
            'company' => $companyY['company'],
            'companyOrg' => $companyY['companyOrg'],
        ], 'active');

        $roleX = $this->createCompanyInternship($schoolA, ['title' => 'Company X Role']);
        $roleY = Internship::query()->create([
            'company_id' => $companyY['company']->id,
            'company_name' => $companyY['company']->company_name,
            'host_type' => 'company',
            'host_name' => $companyY['company']->company_name,
            'title' => 'Company Y Role',
            'status' => 'active',
            'approval_status' => 'approved',
            'slots_available' => 2,
            'requires_resume' => false,
            'required_documents' => ['Endorsement'],
        ]);

        Sanctum::actingAs($schoolA['studentUser']);
        $this->getJson('/api/organizations?type=company')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', (string) $schoolA['companyOrg']->id);
        $this->getJson('/api/internships?status=active')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', (string) $roleX->id);
        $this->getJson('/api/organizations/'.$companyY['companyOrg']->id)
            ->assertForbidden()
            ->assertJsonPath('code', 'agreement_required');
        $this->getJson('/api/organizations/'.$companyY['companyOrg']->id.'/internships')
            ->assertForbidden()
            ->assertJsonPath('code', 'agreement_required');
        $this->getJson('/api/internships/'.$roleY->id)
            ->assertForbidden()
            ->assertJsonPath('code', 'agreement_required');
        $this->postApplication($roleY->id)
            ->assertStatus(422)
            ->assertJsonPath('code', 'agreement_required');
        $this->postApplication($roleX->id)->assertCreated();

        Sanctum::actingAs($schoolB['studentUser']);
        $this->getJson('/api/organizations?type=company')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', (string) $companyY['companyOrg']->id);
        $this->getJson('/api/internships?status=active')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', (string) $roleY->id);
        $this->getJson('/api/organizations/'.$schoolA['companyOrg']->id)
            ->assertForbidden()
            ->assertJsonPath('code', 'agreement_required');
        $this->getJson('/api/internships/'.$roleX->id)->assertForbidden();
        $this->postApplication($roleX->id)
            ->assertStatus(422)
            ->assertJsonPath('code', 'agreement_required');
    }

    /**
     * @return array{
     *   schoolUser: User,
     *   school: School,
     *   schoolOrg: Organization,
     *   companyUser: User,
     *   company: Company,
     *   companyOrg: Organization,
     *   studentUser: User,
     *   student: Student
     * }
     */
    private function createContext(string $hostType = 'company'): array
    {
        $schoolUser = User::factory()->create(['role' => 'school', 'is_active' => true]);
        $schoolSub = Subscription::query()->create(['plan' => 'standard', 'status' => 'active', 'billing_cycle' => 'monthly']);
        $schoolOrg = Organization::query()->create([
            'name' => 'Visibility School',
            'type' => 'school',
            'subscription_id' => $schoolSub->id,
            'owner_user_id' => $schoolUser->id,
            'is_active' => true,
        ]);
        $school = School::query()->create([
            'user_id' => $schoolUser->id,
            'organization_id' => $schoolOrg->id,
            'institution_name' => 'Visibility School',
            'subscription_code' => 'VIS-'.uniqid(),
            'verification_status' => 'approved',
        ]);
        SchoolCourseRequirement::query()->create([
            'school_id' => $school->id,
            'course_name' => 'BSIT',
            'course_name_key' => SchoolCourseRequirement::courseNameKey('BSIT'),
            'required_hours' => 486,
            'eligible_year_levels' => StudentYearLevel::DEFAULT_ELIGIBLE,
        ]);

        $companyUser = User::factory()->create([
            'role' => $hostType === 'municipal_hall' ? 'municipal_hall' : 'company',
            'is_active' => true,
        ]);
        $companySub = Subscription::query()->create(['plan' => 'standard', 'status' => 'active', 'billing_cycle' => 'monthly']);
        $companyOrg = Organization::query()->create([
            'name' => $hostType === 'municipal_hall' ? 'Visibility Municipal Hall' : 'Visibility Company',
            'type' => $hostType,
            'subscription_id' => $companySub->id,
            'owner_user_id' => $companyUser->id,
            'is_active' => true,
        ]);
        $company = Company::query()->create([
            'user_id' => $companyUser->id,
            'organization_id' => $companyOrg->id,
            'company_name' => $companyOrg->name,
            'company_type' => $hostType === 'municipal_hall' ? 'municipal_hall' : null,
            'lgu_type' => $hostType === 'municipal_hall' ? 'municipality' : null,
            'verification_status' => 'approved',
        ]);

        $studentUser = User::factory()->create(['role' => 'student', 'is_active' => true, 'name' => 'Visibility Intern']);
        $student = Student::query()->create([
            'user_id' => $studentUser->id,
            'school_id' => $school->id,
            'organization_id' => $schoolOrg->id,
            'school_name' => $school->institution_name,
            'school_subscription_code' => $school->subscription_code,
            'course' => 'BSIT',
            'year_level' => '4th Year',
        ]);

        return compact(
            'schoolUser',
            'school',
            'schoolOrg',
            'companyUser',
            'company',
            'companyOrg',
            'studentUser',
            'student'
        );
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @param  array<string, mixed>  $overrides
     */
    private function createAgreement(array $ctx, string $status, array $overrides = []): Contract
    {
        return Contract::query()->create(array_merge([
            'school_user_id' => $ctx['schoolUser']->id,
            'school_name' => $ctx['school']->institution_name,
            'company_user_id' => $ctx['companyUser']->id,
            'company_name' => $ctx['company']->company_name,
            'requester_organization_id' => $ctx['schoolOrg']->id,
            'partner_organization_id' => $ctx['companyOrg']->id,
            'requested_by_role' => 'school',
            'requested_by_user_id' => $ctx['schoolUser']->id,
            'partner_user_id' => $ctx['companyUser']->id,
            'status' => $status,
            'subject' => 'Visibility MOA',
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @param  array<string, mixed>  $overrides
     */
    private function createCompanyInternship(array $ctx, array $overrides = []): Internship
    {
        return Internship::query()->create(array_merge([
            'company_id' => $ctx['company']->id,
            'company_name' => $ctx['company']->company_name,
            'host_type' => 'company',
            'host_name' => $ctx['company']->company_name,
            'title' => 'Visibility Role',
            'status' => 'active',
            'approval_status' => 'approved',
            'slots_available' => 3,
            'requires_resume' => false,
            'required_documents' => ['Endorsement'],
        ], $overrides));
    }

    /**
     * @return array{schoolUser: User, school: School, schoolOrg: Organization}
     */
    private function createOtherSchool(): array
    {
        $schoolUser = User::factory()->create(['role' => 'school', 'is_active' => true]);
        $schoolSub = Subscription::query()->create(['plan' => 'standard', 'status' => 'active', 'billing_cycle' => 'monthly']);
        $schoolOrg = Organization::query()->create([
            'name' => 'Other Visibility School',
            'type' => 'school',
            'subscription_id' => $schoolSub->id,
            'owner_user_id' => $schoolUser->id,
            'is_active' => true,
        ]);
        $school = School::query()->create([
            'user_id' => $schoolUser->id,
            'organization_id' => $schoolOrg->id,
            'institution_name' => 'Other Visibility School',
            'subscription_code' => 'OTH-'.uniqid(),
            'verification_status' => 'approved',
        ]);

        return compact('schoolUser', 'school', 'schoolOrg');
    }

    /**
     * @return array{
     *   schoolUser: User,
     *   school: School,
     *   schoolOrg: Organization,
     *   studentUser: User,
     *   student: Student
     * }
     */
    private function createOtherSchoolWithStudent(): array
    {
        $school = $this->createOtherSchool();
        SchoolCourseRequirement::query()->create([
            'school_id' => $school['school']->id,
            'course_name' => 'BSIT',
            'course_name_key' => SchoolCourseRequirement::courseNameKey('BSIT'),
            'required_hours' => 486,
            'eligible_year_levels' => StudentYearLevel::DEFAULT_ELIGIBLE,
        ]);
        $studentUser = User::factory()->create(['role' => 'student', 'is_active' => true, 'name' => 'School B Intern']);
        $student = Student::query()->create([
            'user_id' => $studentUser->id,
            'school_id' => $school['school']->id,
            'organization_id' => $school['schoolOrg']->id,
            'school_name' => $school['school']->institution_name,
            'school_subscription_code' => $school['school']->subscription_code,
            'course' => 'BSIT',
            'year_level' => '4th Year',
        ]);

        return array_merge($school, compact('studentUser', 'student'));
    }

    /**
     * @return array{companyUser: User, company: Company, companyOrg: Organization}
     */
    private function createStandaloneCompany(string $name): array
    {
        $companyUser = User::factory()->create(['role' => 'company', 'is_active' => true]);
        $companySub = Subscription::query()->create(['plan' => 'standard', 'status' => 'active', 'billing_cycle' => 'monthly']);
        $companyOrg = Organization::query()->create([
            'name' => $name,
            'type' => 'company',
            'subscription_id' => $companySub->id,
            'owner_user_id' => $companyUser->id,
            'is_active' => true,
        ]);
        $company = Company::query()->create([
            'user_id' => $companyUser->id,
            'organization_id' => $companyOrg->id,
            'company_name' => $name,
            'verification_status' => 'approved',
        ]);

        return compact('companyUser', 'company', 'companyOrg');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createOtherCompanyInternship(array $overrides = []): Internship
    {
        $companyUser = User::factory()->create(['role' => 'company', 'is_active' => true]);
        $companySub = Subscription::query()->create(['plan' => 'standard', 'status' => 'active', 'billing_cycle' => 'monthly']);
        $companyOrg = Organization::query()->create([
            'name' => 'Outsider Co',
            'type' => 'company',
            'subscription_id' => $companySub->id,
            'owner_user_id' => $companyUser->id,
            'is_active' => true,
        ]);
        $company = Company::query()->create([
            'user_id' => $companyUser->id,
            'organization_id' => $companyOrg->id,
            'company_name' => 'Outsider Co',
            'verification_status' => 'approved',
        ]);

        return Internship::query()->create(array_merge([
            'company_id' => $company->id,
            'company_name' => $company->company_name,
            'host_type' => 'company',
            'host_name' => $company->company_name,
            'title' => 'Outsider Role',
            'status' => 'active',
            'approval_status' => 'approved',
            'slots_available' => 2,
            'requires_resume' => false,
            'required_documents' => ['Endorsement'],
        ], $overrides));
    }
}
