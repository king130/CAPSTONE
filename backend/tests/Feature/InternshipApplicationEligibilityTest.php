<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Internship;
use App\Models\Organization;
use App\Models\School;
use App\Models\Student;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InternshipApplicationEligibilityTest extends TestCase
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
     *   studentUser: User,
     *   student: Student
     * }
     */
    private function createLinkedSchoolCompanyStudent(): array
    {
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
            'name' => 'Eligibility School Org',
            'type' => 'school',
            'subscription_id' => $schoolSub->id,
            'owner_user_id' => $schoolUser->id,
            'settings' => [],
            'is_active' => true,
        ]);
        $school = School::query()->create([
            'user_id' => $schoolUser->id,
            'organization_id' => $schoolOrg->id,
            'institution_name' => 'Eligibility School',
            'subscription_code' => 'ELG-'.strtoupper(substr(uniqid(), -4)),
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
            'name' => 'Eligibility Company Org',
            'type' => 'company',
            'subscription_id' => $companySub->id,
            'owner_user_id' => $companyUser->id,
            'settings' => [],
            'is_active' => true,
        ]);
        $company = Company::query()->create([
            'user_id' => $companyUser->id,
            'organization_id' => $companyOrg->id,
            'company_name' => 'Eligibility Company',
            'verification_status' => 'approved',
        ]);

        $studentUser = User::factory()->create([
            'role' => 'student',
            'email' => 'student.'.uniqid('', true).'@example.com',
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
            'studentUser',
            'student'
        );
    }

    private function createActiveAgreement(User $schoolUser, School $school, User $companyUser, Company $company): Contract
    {
        return Contract::query()->create([
            'school_user_id' => $schoolUser->id,
            'school_name' => $school->institution_name,
            'company_user_id' => $companyUser->id,
            'company_name' => $company->company_name,
            'requested_by_role' => 'school',
            'requested_by_user_id' => $schoolUser->id,
            'partner_user_id' => $companyUser->id,
            'status' => 'active',
            'subject' => 'Eligibility MOA',
        ]);
    }

    public function test_company_internship_without_active_agreement_is_rejected(): void
    {
        $ctx = $this->createLinkedSchoolCompanyStudent();
        $internship = Internship::query()->create([
            'company_id' => $ctx['company']->id,
            'host_type' => 'company',
            'title' => 'No Agreement Internship',
            'status' => 'active',
        ]);

        Sanctum::actingAs($ctx['studentUser']);
        $before = Application::query()->count();

        $response = $this->postJson('/api/applications', [
            'internship_id' => $internship->id,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('code', 'agreement_required');
        $this->assertSame($before, Application::query()->count());
    }

    public function test_company_internship_with_active_agreement_is_accepted(): void
    {
        $ctx = $this->createLinkedSchoolCompanyStudent();
        $this->createActiveAgreement($ctx['schoolUser'], $ctx['school'], $ctx['companyUser'], $ctx['company']);
        $internship = Internship::query()->create([
            'company_id' => $ctx['company']->id,
            'host_type' => 'company',
            'title' => 'Agreed Internship',
            'status' => 'active',
        ]);

        Sanctum::actingAs($ctx['studentUser']);
        $before = Application::query()->count();

        $response = $this->postJson('/api/applications', [
            'internship_id' => $internship->id,
        ]);

        $response->assertCreated();
        $this->assertSame($before + 1, Application::query()->count());
        $this->assertDatabaseHas('applications', [
            'internship_id' => $internship->id,
            'student_id' => $ctx['student']->id,
            'status' => 'submitted',
        ]);
    }

    public function test_inactive_internship_is_rejected(): void
    {
        $ctx = $this->createLinkedSchoolCompanyStudent();
        $this->createActiveAgreement($ctx['schoolUser'], $ctx['school'], $ctx['companyUser'], $ctx['company']);
        $internship = Internship::query()->create([
            'company_id' => $ctx['company']->id,
            'host_type' => 'company',
            'title' => 'Closed Internship',
            'status' => 'closed',
        ]);

        Sanctum::actingAs($ctx['studentUser']);
        $before = Application::query()->count();

        $response = $this->postJson('/api/applications', [
            'internship_id' => $internship->id,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('code', 'internship_not_active');
        $this->assertSame($before, Application::query()->count());
    }

    public function test_school_hosted_internship_from_another_school_is_rejected(): void
    {
        $ctx = $this->createLinkedSchoolCompanyStudent();

        $otherSchoolUser = User::factory()->create([
            'role' => 'school',
            'email' => 'other.school.'.uniqid('', true).'@example.com',
            'is_active' => true,
        ]);
        $otherSub = Subscription::query()->create([
            'plan' => 'standard',
            'status' => 'active',
            'billing_cycle' => 'monthly',
        ]);
        $otherOrg = Organization::query()->create([
            'name' => 'Other School Org',
            'type' => 'school',
            'subscription_id' => $otherSub->id,
            'owner_user_id' => $otherSchoolUser->id,
            'settings' => [],
            'is_active' => true,
        ]);
        $otherSchool = School::query()->create([
            'user_id' => $otherSchoolUser->id,
            'organization_id' => $otherOrg->id,
            'institution_name' => 'Other School',
            'subscription_code' => 'OTH-'.strtoupper(substr(uniqid(), -4)),
            'verification_status' => 'approved',
        ]);

        $internship = Internship::query()->create([
            'school_id' => $otherSchool->id,
            'host_type' => 'school',
            'title' => 'Other School Internship',
            'status' => 'active',
        ]);

        Sanctum::actingAs($ctx['studentUser']);
        $before = Application::query()->count();

        $response = $this->postJson('/api/applications', [
            'internship_id' => $internship->id,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('code', 'school_internship_mismatch');
        $this->assertSame($before, Application::query()->count());
    }

    public function test_school_hosted_internship_from_own_school_is_accepted(): void
    {
        $ctx = $this->createLinkedSchoolCompanyStudent();
        $internship = Internship::query()->create([
            'school_id' => $ctx['school']->id,
            'host_type' => 'school',
            'title' => 'Own School Internship',
            'status' => 'active',
        ]);

        Sanctum::actingAs($ctx['studentUser']);

        $response = $this->postJson('/api/applications', [
            'internship_id' => $internship->id,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('applications', [
            'internship_id' => $internship->id,
            'student_id' => $ctx['student']->id,
            'status' => 'submitted',
        ]);
    }

    public function test_eligible_listing_matches_application_eligibility_rules(): void
    {
        $ctx = $this->createLinkedSchoolCompanyStudent();

        $unrelatedCompanyUser = User::factory()->create([
            'role' => 'company',
            'email' => 'unrelated.company.'.uniqid('', true).'@example.com',
            'is_active' => true,
        ]);
        $unrelatedSub = Subscription::query()->create([
            'plan' => 'standard',
            'status' => 'active',
            'billing_cycle' => 'monthly',
        ]);
        $unrelatedOrg = Organization::query()->create([
            'name' => 'Unrelated Company Org',
            'type' => 'company',
            'subscription_id' => $unrelatedSub->id,
            'owner_user_id' => $unrelatedCompanyUser->id,
            'settings' => [],
            'is_active' => true,
        ]);
        $unrelatedCompany = Company::query()->create([
            'user_id' => $unrelatedCompanyUser->id,
            'organization_id' => $unrelatedOrg->id,
            'company_name' => 'Unrelated Company',
            'verification_status' => 'approved',
        ]);

        $withoutAgreement = Internship::query()->create([
            'company_id' => $unrelatedCompany->id,
            'host_type' => 'company',
            'title' => 'Hidden Without Agreement',
            'status' => 'active',
        ]);

        $this->createActiveAgreement($ctx['schoolUser'], $ctx['school'], $ctx['companyUser'], $ctx['company']);

        $withAgreement = Internship::query()->create([
            'company_id' => $ctx['company']->id,
            'host_type' => 'company',
            'title' => 'Visible With Agreement',
            'status' => 'active',
        ]);

        $ownSchoolHosted = Internship::query()->create([
            'school_id' => $ctx['school']->id,
            'host_type' => 'school',
            'title' => 'Own School Hosted',
            'status' => 'active',
        ]);

        $otherSchoolUser = User::factory()->create([
            'role' => 'school',
            'email' => 'foreign.school.'.uniqid('', true).'@example.com',
            'is_active' => true,
        ]);
        $otherSub = Subscription::query()->create([
            'plan' => 'standard',
            'status' => 'active',
            'billing_cycle' => 'monthly',
        ]);
        $otherOrg = Organization::query()->create([
            'name' => 'Foreign School Org',
            'type' => 'school',
            'subscription_id' => $otherSub->id,
            'owner_user_id' => $otherSchoolUser->id,
            'settings' => [],
            'is_active' => true,
        ]);
        $otherSchool = School::query()->create([
            'user_id' => $otherSchoolUser->id,
            'organization_id' => $otherOrg->id,
            'institution_name' => 'Foreign School',
            'subscription_code' => 'FRN-'.strtoupper(substr(uniqid(), -4)),
            'verification_status' => 'approved',
        ]);
        $foreignSchoolHosted = Internship::query()->create([
            'school_id' => $otherSchool->id,
            'host_type' => 'school',
            'title' => 'Foreign School Hosted',
            'status' => 'active',
        ]);

        $inactive = Internship::query()->create([
            'company_id' => $ctx['company']->id,
            'host_type' => 'company',
            'title' => 'Inactive Agreed Internship',
            'status' => 'draft',
        ]);

        Sanctum::actingAs($ctx['studentUser']);

        $eligible = $this->getJson('/api/internships/eligible');
        $eligible->assertOk();
        $eligibleIds = collect($eligible->json('data'))->pluck('id')->map(fn ($id) => (string) $id)->all();

        $this->assertContains((string) $withAgreement->id, $eligibleIds);
        $this->assertContains((string) $ownSchoolHosted->id, $eligibleIds);
        $this->assertNotContains((string) $withoutAgreement->id, $eligibleIds);
        $this->assertNotContains((string) $foreignSchoolHosted->id, $eligibleIds);
        $this->assertNotContains((string) $inactive->id, $eligibleIds);

        $this->postJson('/api/applications', ['internship_id' => $withoutAgreement->id])->assertStatus(422);
        $this->postJson('/api/applications', ['internship_id' => $foreignSchoolHosted->id])->assertStatus(422);
        $this->postJson('/api/applications', ['internship_id' => $inactive->id])->assertStatus(422);
        $this->postJson('/api/applications', ['internship_id' => $withAgreement->id])->assertCreated();
    }
}
