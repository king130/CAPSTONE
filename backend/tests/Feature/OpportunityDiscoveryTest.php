<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Contract;
use App\Models\Internship;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Role;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OpportunityDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_students_and_schools_browse_companies_without_private_fields(): void
    {
        Storage::fake('public');
        $companyA = $this->makeCompany('Acme Labs', 'Software', 'Imus City');
        $companyB = $this->makeCompany('North Clinic', 'Healthcare', 'Bacoor City');
        $this->giveAvatar($companyA['user'], 'a.jpg');
        $this->giveAvatar($companyB['user'], 'b.jpg');

        Internship::query()->create([
            'company_id' => $companyA['company']->id,
            'company_name' => 'Acme Labs',
            'host_type' => 'company',
            'host_name' => 'Acme Labs',
            'title' => 'Visible Intern',
            'status' => 'active',
            'slots_available' => 4,
        ]);
        Internship::query()->create([
            'company_id' => $companyA['company']->id,
            'company_name' => 'Acme Labs',
            'host_type' => 'company',
            'host_name' => 'Acme Labs',
            'title' => 'Draft Intern',
            'status' => 'draft',
            'slots_available' => 2,
        ]);

        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        Sanctum::actingAs($student);
        // Students without a partnered school agreement do not browse host directories.
        $this->getJson('/api/organizations?type=company&q=Acme')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
        $this->getJson('/api/organizations?type=school')->assertForbidden();

        $school = $this->makeSchool('Cavite State', 'Indang');
        Sanctum::actingAs($school['user']);
        $response = $this->getJson('/api/organizations?type=company&has_openings=1')->assertOk();
        $response->assertJsonPath('data.0.name', 'Acme Labs')
            ->assertJsonPath('meta.total', 1);
        $this->assertStringContainsString('/profile/avatar/'.$companyA['user']->id, (string) $response->json('data.0.logoUrl'));
        $payload = $response->json('data.0');
        $this->assertArrayNotHasKey('email', $payload);
        $this->assertArrayNotHasKey('settings', $payload);
        $this->assertArrayNotHasKey('subscriptionStatus', $payload);
        $this->assertArrayNotHasKey('memberCount', $payload);
        $this->getJson('/api/organizations?type=school')->assertForbidden();
    }

    public function test_companies_browse_schools_without_student_or_private_fields(): void
    {
        $schoolA = $this->makeSchool('Alpha Institute', 'Imus City');
        $this->makeSchool('Beta Academy', 'Silang');
        $company = $this->makeCompany('Viewer Co', 'Software', 'Imus City');

        Sanctum::actingAs($company['user']);
        $response = $this->getJson('/api/organizations?type=school&city=Imus City')->assertOk();
        $response->assertJsonPath('data.0.name', 'Alpha Institute');
        $response->assertJsonPath('meta.total', 1);
        $card = $response->json('data.0');
        $this->assertArrayNotHasKey('email', $card);
        $this->assertArrayNotHasKey('official_school_email', $card);
        $this->assertArrayNotHasKey('students', $card);
        $this->assertArrayNotHasKey('settings', $card);

        $this->getJson('/api/organizations?type=company')->assertForbidden();

        $this->getJson('/api/organizations/'.$schoolA['organization']->id)
            ->assertOk()
            ->assertJsonMissingPath('data.settings')
            ->assertJsonMissingPath('data.email');
    }

    public function test_pending_organizations_are_not_discoverable(): void
    {
        $this->makeCompany('Hidden Co', 'Software', 'Imus City', 'pending');
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        Sanctum::actingAs($student);

        $this->getJson('/api/organizations?type=company')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_company_and_municipal_browse_endpoints_are_isolated(): void
    {
        $this->makeCompany('Private Corp', 'Software', 'Imus City');
        $hall = $this->makeMunicipalHall('Naic Municipal Hall', 'Naic');

        // Schools browse all approved host types; type filters stay isolated.
        $school = $this->makeSchool('Browse School', 'Imus City');
        Sanctum::actingAs($school['user']);

        $companies = $this->getJson('/api/organizations?type=company')->assertOk();
        $companies->assertJsonPath('meta.total', 1);
        $companies->assertJsonPath('data.0.name', 'Private Corp');
        $companies->assertJsonPath('data.0.type', 'company');

        $municipal = $this->getJson('/api/organizations?type=municipal_hall')->assertOk();
        $municipal->assertJsonPath('meta.total', 1);
        $municipal->assertJsonPath('data.0.name', 'Naic Municipal Hall');
        $municipal->assertJsonPath('data.0.type', 'municipal_hall');
        $municipal->assertJsonPath('data.0.id', (string) $hall['organization']->id);

        $this->getJson('/api/organizations?type=school')->assertForbidden();
    }

    public function test_school_company_directory_defaults_to_course_aligned_with_partner_and_unspecified_exceptions(): void
    {
        $school = $this->makeSchool('Aligned Viewer School', 'Imus City');
        $school['user']->forceFill(['profile' => ['courses' => ['BSHM', 'BSIT']]])->save();

        $aligned = $this->makeCompany('Aligned Host', 'Hospitality', 'Imus City');
        $aligned['user']->forceFill(['profile' => ['courses' => ['bshm']]])->save();

        $none = $this->makeCompany('No Overlap Host', 'Software', 'Bacoor City');
        $none['user']->forceFill(['profile' => ['courses' => ['BS Computer Science']]])->save();

        $unspecified = $this->makeCompany('Unspecified Host', 'Retail', 'Silang');
        $unspecified['user']->forceFill(['profile' => []])->save();

        $partneredNone = $this->makeCompany('Partnered No Overlap', 'Finance', 'Naic');
        $partneredNone['user']->forceFill(['profile' => ['courses' => ['BSA']]])->save();
        Contract::query()->create([
            'school_user_id' => $school['user']->id,
            'school_name' => $school['organization']->name,
            'company_user_id' => $partneredNone['user']->id,
            'company_name' => $partneredNone['organization']->name,
            'requested_by_role' => 'school',
            'requester_organization_id' => $school['organization']->id,
            'partner_organization_id' => $partneredNone['organization']->id,
            'status' => 'active',
            'subject' => 'OJT Partnership',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
        ]);

        Sanctum::actingAs($school['user']);

        $alignedResponse = $this->getJson('/api/organizations?type=company&course_alignment=aligned')->assertOk();
        $alignedResponse->assertJsonPath('meta.course_alignment', 'aligned');
        $alignedResponse->assertJsonPath('meta.viewer_has_courses', true);
        $alignedResponse->assertJsonPath('meta.catalog_total', 4);
        $alignedResponse->assertJsonPath('meta.aligned', 1);
        $alignedResponse->assertJsonPath('meta.hidden_count', 1);
        $alignedResponse->assertJsonPath('meta.total', 3);

        $names = collect($alignedResponse->json('data'))->pluck('name')->all();
        $this->assertContains('Aligned Host', $names);
        $this->assertContains('Unspecified Host', $names);
        $this->assertContains('Partnered No Overlap', $names);
        $this->assertNotContains('No Overlap Host', $names);

        $alignedCard = collect($alignedResponse->json('data'))->firstWhere('name', 'Aligned Host');
        $this->assertSame('aligned', $alignedCard['alignment']);
        $this->assertSame(['BSHM'], $alignedCard['sharedCourses']);

        $allResponse = $this->getJson('/api/organizations?type=company&course_alignment=all')->assertOk();
        $allResponse->assertJsonPath('meta.total', 4);
        $allResponse->assertJsonPath('meta.hidden_count', 0);
    }

    public function test_company_school_directory_course_alignment_mirrors_school_side(): void
    {
        $company = $this->makeCompany('Courseful Co', 'Software', 'Imus City');
        $company['user']->forceFill(['profile' => ['courses' => ['BSIT']]])->save();

        $alignedSchool = $this->makeSchool('IT School', 'Imus City');
        $alignedSchool['user']->forceFill(['profile' => ['courses' => ['BS Information Technology', 'BSIT']]])->save();

        $otherSchool = $this->makeSchool('Hotel School', 'Bacoor City');
        $otherSchool['user']->forceFill(['profile' => ['courses' => ['BSHM']]])->save();

        Sanctum::actingAs($company['user']);
        $response = $this->getJson('/api/organizations?type=school&course_alignment=aligned')->assertOk();
        $response->assertJsonPath('meta.total', 1);
        $response->assertJsonPath('data.0.name', 'IT School');
        $response->assertJsonPath('data.0.alignment', 'aligned');
    }

    public function test_non_owner_member_browse_uses_organization_owner_courses_only(): void
    {
        $school = $this->makeSchool('Owner Courses School', 'Imus City');
        $school['user']->forceFill(['profile' => ['courses' => ['BSHM']]])->save();

        $staffRole = Role::query()->create([
            'tenant_id' => $school['organization']->id,
            'name' => 'Coordinator',
            'slug' => 'coordinator_'.uniqid(),
            'scope' => 'organization',
            'organization_type' => 'school',
        ]);

        OrganizationMembership::query()->create([
            'organization_id' => $school['organization']->id,
            'user_id' => $school['user']->id,
            'role_id' => $staffRole->id,
            'status' => 'active',
            'title' => 'Owner',
        ]);

        $member = User::factory()->create([
            'role' => 'school',
            'is_active' => true,
            'tenant_id' => $school['organization']->id,
            'role_id' => $staffRole->id,
            // Member personal courses must not drive browse alignment.
            'profile' => ['courses' => ['BSIT']],
        ]);
        OrganizationMembership::query()->create([
            'organization_id' => $school['organization']->id,
            'user_id' => $member->id,
            'role_id' => $staffRole->id,
            'status' => 'active',
            'title' => 'Staff',
        ]);

        $bshmHost = $this->makeCompany('BSHM Host', 'Hospitality', 'Imus City');
        $bshmHost['user']->forceFill(['profile' => ['courses' => ['BSHM']]])->save();

        $bsitHost = $this->makeCompany('BSIT Host', 'Software', 'Bacoor City');
        $bsitHost['user']->forceFill(['profile' => ['courses' => ['BSIT']]])->save();

        Sanctum::actingAs($member);
        $response = $this->getJson('/api/organizations?type=company&course_alignment=aligned')->assertOk();
        $response->assertJsonPath('meta.viewer_has_courses', true);
        $response->assertJsonPath('meta.aligned', 1);
        $response->assertJsonPath('meta.total', 1);
        $response->assertJsonPath('data.0.name', 'BSHM Host');
        $response->assertJsonPath('data.0.sharedCourses', ['BSHM']);
    }

    /**
     * @return array{user: User, organization: Organization, company: Company}
     */
    private function makeCompany(string $name, string $industry, string $city, string $verification = 'approved'): array
    {
        $user = User::factory()->create(['role' => 'company', 'is_active' => true, 'email' => strtolower(str_replace(' ', '', $name)).uniqid('', true).'@example.com']);
        $organization = Organization::query()->create([
            'name' => $name,
            'type' => 'company',
            'owner_user_id' => $user->id,
            'is_active' => true,
            'city' => $city,
            'industry' => $industry,
            'address' => 'Sample, '.$city.', Cavite',
        ]);
        $company = Company::query()->create([
            'user_id' => $user->id,
            'organization_id' => $organization->id,
            'company_name' => $name,
            'industry_type' => $industry,
            'company_address' => 'Sample, '.$city.', Cavite',
            'company_email' => 'private@'.$organization->id.'.example',
            'verification_status' => $verification,
        ]);

        return compact('user', 'organization', 'company');
    }

    /**
     * @return array{user: User, organization: Organization, school: School}
     */
    private function makeSchool(string $name, string $city): array
    {
        $user = User::factory()->create(['role' => 'school', 'is_active' => true]);
        $organization = Organization::query()->create([
            'name' => $name,
            'type' => 'school',
            'owner_user_id' => $user->id,
            'is_active' => true,
            'city' => $city,
            'address' => 'Campus, '.$city.', Cavite',
        ]);
        $school = School::query()->create([
            'user_id' => $user->id,
            'organization_id' => $organization->id,
            'institution_name' => $name,
            'school_address' => 'Campus, '.$city.', Cavite',
            'official_school_email' => 'registrar@'.$organization->id.'.example',
            'verification_status' => 'approved',
        ]);

        return compact('user', 'organization', 'school');
    }

    private function giveAvatar(User $user, string $filename): void
    {
        $path = 'avatars/'.$user->id.'/'.$filename;
        Storage::disk('public')->put($path, 'avatar');
        $user->forceFill(['profile' => ['avatarPath' => $path]])->save();
    }

    /**
     * @return array{user: User, organization: Organization, company: Company}
     */
    private function makeMunicipalHall(string $name, string $city): array
    {
        $user = User::factory()->create([
            'role' => 'municipal_hall',
            'is_active' => true,
            'email' => strtolower(str_replace(' ', '', $name)).uniqid('', true).'@example.com',
        ]);
        $organization = Organization::query()->create([
            'name' => $name,
            'type' => 'municipal_hall',
            'owner_user_id' => $user->id,
            'is_active' => true,
            'city' => $city,
            'address' => 'Hall, '.$city.', Cavite',
        ]);
        $company = Company::query()->create([
            'user_id' => $user->id,
            'organization_id' => $organization->id,
            'company_name' => $name,
            'company_type' => 'municipal_hall',
            'lgu_type' => 'municipality',
            'industry_type' => 'Local Government',
            'company_address' => 'Hall, '.$city.', Cavite',
            'company_email' => 'private@'.$organization->id.'.example',
            'verification_status' => 'approved',
        ]);

        return compact('user', 'organization', 'company');
    }
}
