<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Internship;
use App\Models\Organization;
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

        $response = $this->getJson('/api/organizations?type=company&q=Acme')->assertOk();
        $response->assertJsonPath('data.0.name', 'Acme Labs');
        $response->assertJsonPath('data.0.openOpportunities', 1);
        $this->assertStringContainsString('/profile/avatar/'.$companyA['user']->id, (string) $response->json('data.0.logoUrl'));
        $this->assertStringNotContainsString('/profile/avatar/'.$companyB['user']->id, (string) $response->json('data.0.logoUrl'));
        $payload = $response->json('data.0');
        $this->assertArrayNotHasKey('email', $payload);
        $this->assertArrayNotHasKey('settings', $payload);
        $this->assertArrayNotHasKey('subscriptionStatus', $payload);
        $this->assertArrayNotHasKey('memberCount', $payload);

        $this->getJson('/api/organizations?type=school')->assertForbidden();

        $school = $this->makeSchool('Cavite State', 'Indang');
        Sanctum::actingAs($school['user']);
        $this->getJson('/api/organizations?type=company&has_openings=1')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Acme Labs')
            ->assertJsonPath('meta.total', 1);
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
}
