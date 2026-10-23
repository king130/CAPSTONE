<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TenantSettingsAddressTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_stores_each_organizations_cavite_address(): void
    {
        $first = $this->registerCompany('TechCorp', 'Dasmariñas', 'techcorp.address.'.uniqid('', true).'@example.com', 'Fatima III', 'Dasmarinas City');
        $second = $this->registerCompany('Another Company', 'Imus', 'another.address.'.uniqid('', true).'@example.com', 'Bayan Luma', 'Imus City');

        $first->assertCreated();
        $second->assertCreated();

        $tech = Organization::query()->where('name', 'TechCorp')->firstOrFail();
        $other = Organization::query()->where('name', 'Another Company')->firstOrFail();

        $this->assertSame('Fatima III, Dasmariñas, Dasmarinas City, Cavite', $tech->address);
        $this->assertSame('Dasmarinas City', $tech->city);
        $this->assertSame('Bayan Luma, Imus, Imus City, Cavite', $other->address);
        $this->assertSame('Imus City', $other->city);
        $this->assertNotSame($tech->address, $other->address);

        $this->getJson('/api/organizations/'.$tech->id)
            ->assertOk()
            ->assertJsonPath('data.address', 'Fatima III, Dasmariñas, Dasmarinas City, Cavite')
            ->assertJsonPath('data.city', 'Dasmarinas City')
            ->assertJsonMissingPath('data.settings');

        $this->getJson('/api/organizations/'.$other->id)
            ->assertOk()
            ->assertJsonPath('data.city', 'Imus City')
            ->assertJsonPath('data.address', 'Bayan Luma, Imus, Imus City, Cavite');
    }

    public function test_registration_rejects_an_address_outside_cavite(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'email' => 'manila.'.uniqid('', true).'@example.com',
            'password' => 'Company@2026',
            'fullName' => 'Manila Owner',
            'role' => 'company',
            'profile' => [
                'companyName' => 'Manila Co',
                'companyAddress' => '1 Ayala Avenue, Makati, Metro Manila',
                'locationArea' => 'Makati',
            ],
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('organizations', ['name' => 'Manila Co']);
    }

    public function test_public_profile_uses_the_registered_company_address_when_organization_address_is_empty(): void
    {
        $user = User::factory()->create([
            'role' => 'company',
            'email' => 'legacy.'.uniqid('', true).'@example.com',
            'is_active' => true,
        ]);
        $organization = Organization::query()->create([
            'name' => 'Legacy Co',
            'type' => 'company',
            'owner_user_id' => $user->id,
            'is_active' => true,
            'settings' => ['required_ojt_hours' => 500, 'secret' => 'hidden'],
        ]);
        \App\Models\Company::query()->create([
            'user_id' => $user->id,
            'organization_id' => $organization->id,
            'company_name' => 'Legacy Co',
            'company_address' => 'Fatima III, Dasmarinas City, Cavite',
            'verification_status' => 'approved',
        ]);

        $response = $this->getJson('/api/organizations/'.$organization->id);
        $response->assertOk();
        $response->assertJsonPath('data.address', 'Fatima III, Dasmarinas City, Cavite');
        $response->assertJsonPath('data.city', 'Dasmarinas City');
        $response->assertJsonMissingPath('data.settings');
        $this->assertNull($organization->fresh()->address);
    }

    public function test_school_can_manage_required_hours_and_company_cannot(): void
    {
        $school = $this->registerSchool();
        $school->assertCreated();
        $schoolUser = User::query()->where('email', $school->json('user.email'))->firstOrFail();
        School::query()->where('user_id', $schoolUser->id)->update(['verification_status' => 'approved']);
        $permission = Permission::query()->updateOrCreate(
            ['key' => 'org.approve_ojt_logs'],
            ['scope' => 'organization', 'description' => 'Approve OJT logs']
        );
        $schoolUser->primaryOrganizationMembership()?->role?->permissions()->syncWithoutDetaching([$permission->id]);

        Sanctum::actingAs($schoolUser->fresh());
        $this->getJson('/api/ojt-logs/required-hours')
            ->assertOk()
            ->assertJsonPath('data.requiredHours', 500);
        $this->patchJson('/api/ojt-logs/required-hours', ['requiredHours' => 486])
            ->assertOk()
            ->assertJsonPath('data.requiredHours', 486);

        $company = $this->registerCompany('Hours Co', 'Silang', 'hours.'.uniqid('', true).'@example.com', 'Poblacion', 'Silang');
        $company->assertCreated();
        $companyUser = User::query()->where('email', $company->json('user.email'))->firstOrFail();
        Company::query()->where('user_id', $companyUser->id)->update(['verification_status' => 'approved']);
        $companyUser->primaryOrganizationMembership()?->role?->permissions()->syncWithoutDetaching([$permission->id]);

        Sanctum::actingAs($companyUser->fresh());
        $this->getJson('/api/ojt-logs/required-hours')->assertForbidden();
        $this->patchJson('/api/ojt-logs/required-hours', ['requiredHours' => 100])->assertForbidden();
        $this->getJson('/api/school-course-requirements')->assertForbidden();
        $this->assertNotSame(100, (int) ($companyUser->fresh()->activeOrganization()?->settings['required_ojt_hours'] ?? 0));
    }

    private function registerCompany(string $name, string $barangay, string $email, string $street, string $city)
    {
        return $this->postJson('/api/auth/register', [
            'email' => $email,
            'password' => 'Company@2026',
            'fullName' => $name.' Owner',
            'role' => 'company',
            'profile' => [
                'companyName' => $name,
                'industryType' => 'Technology',
                'companyAddress' => $street.', '.$barangay.', '.$city.', Cavite',
                'locationArea' => $city,
                'barangay' => $barangay,
            ],
        ]);
    }

    private function registerSchool()
    {
        return $this->postJson('/api/auth/register', [
            'email' => 'school.hours.'.uniqid('', true).'@example.com',
            'password' => 'School@2026',
            'fullName' => 'School Owner',
            'role' => 'school',
            'profile' => [
                'institutionName' => 'Cavite Hours Academy',
                'schoolAddress' => 'Indang Campus, Indang, Cavite',
                'locationArea' => 'Indang',
                'barangay' => 'Poblacion',
            ],
        ]);
    }
}
