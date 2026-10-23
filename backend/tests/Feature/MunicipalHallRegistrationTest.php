<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MunicipalHallRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_municipal_hall_can_register_and_maps_to_company_workspace(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'fullName' => 'Juan Dela Cruz',
            'email' => 'lgu.admin@naic.cavite.ph',
            'password' => 'SecurePass1',
            'role' => 'municipal_hall',
            'profile' => [
                'officialName' => 'Municipality of Naic — Municipal Hall',
                'lguType' => 'municipality',
                'representativeName' => 'Juan Dela Cruz',
                'representativePosition' => 'Municipal Administrator',
                'locationArea' => 'Naic',
                'barangay' => 'Ibayo Silangan',
                'companyAddress' => 'Municipal Compound, Naic, Cavite',
                'contactNumber' => '09171234567',
            ],
            'subscriptionPlan' => 'free',
            'billingCycle' => 'monthly',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('user.role', 'company');
        $response->assertJsonPath('user.activeOrganization.type', 'municipal_hall');
        $response->assertJsonPath('user.verificationStatus', 'pending');

        $this->assertDatabaseHas('organizations', [
            'type' => 'municipal_hall',
            'name' => 'Municipality of Naic — Municipal Hall',
        ]);
        $this->assertDatabaseHas('companies', [
            'company_name' => 'Municipality of Naic — Municipal Hall',
            'lgu_type' => 'municipality',
            'city_municipality' => 'Naic',
            'verification_status' => 'pending',
        ]);
    }

    public function test_duplicate_municipal_hall_name_and_city_is_rejected(): void
    {
        $this->postJson('/api/auth/register', [
            'fullName' => 'First Admin',
            'email' => 'first.lgu@example.com',
            'password' => 'SecurePass1',
            'role' => 'municipal_hall',
            'profile' => [
                'officialName' => 'Municipality of Tanza — Municipal Hall',
                'lguType' => 'municipality',
                'representativeName' => 'First Admin',
                'representativePosition' => 'HR Officer',
                'locationArea' => 'Tanza',
                'barangay' => 'Daang Amaya I',
                'contactNumber' => '09171234567',
            ],
        ])->assertCreated();

        $duplicate = $this->postJson('/api/auth/register', [
            'fullName' => 'Second Admin',
            'email' => 'second.lgu@example.com',
            'password' => 'SecurePass1',
            'role' => 'municipal_hall',
            'profile' => [
                'officialName' => 'Municipality of Tanza — Municipal Hall',
                'lguType' => 'municipality',
                'representativeName' => 'Second Admin',
                'representativePosition' => 'HR Officer',
                'locationArea' => 'Tanza',
                'barangay' => 'Daang Amaya I',
                'contactNumber' => '09179876543',
            ],
        ]);

        $duplicate->assertStatus(422);
        $this->assertSame(1, Organization::query()->where('type', 'municipal_hall')->count());
    }

    public function test_staff_inherit_municipal_hall_verification_status(): void
    {
        $this->postJson('/api/auth/register', [
            'fullName' => 'LGU Owner',
            'email' => 'owner.lgu@example.com',
            'password' => 'SecurePass1',
            'role' => 'municipal_hall',
            'profile' => [
                'officialName' => 'Municipality of Rosario — Municipal Hall',
                'lguType' => 'municipality',
                'representativeName' => 'LGU Owner',
                'representativePosition' => "Mayor's Office",
                'locationArea' => 'Rosario',
                'barangay' => 'Bagbag',
                'contactNumber' => '09171112222',
            ],
        ])->assertCreated();

        $org = Organization::query()->where('type', 'municipal_hall')->firstOrFail();
        $company = Company::query()->where('organization_id', $org->id)->firstOrFail();
        $company->update(['verification_status' => 'approved']);
        // Free plan allows one host seat (the owner). Staff needs a paid host seat limit.
        $org->subscription?->forceFill(['plan' => 'standard', 'status' => 'active'])->save();

        $staff = User::factory()->create([
            'role' => 'company',
            'email' => 'staff.lgu@example.com',
            'is_active' => true,
        ]);
        $adminRole = $org->roles()->where('slug', 'company_admin')->firstOrFail();
        OrganizationMembership::query()->create([
            'organization_id' => $org->id,
            'user_id' => $staff->id,
            'role_id' => $adminRole->id,
            'status' => 'active',
            'title' => 'HR Staff',
        ]);
        $staff->forceFill(['tenant_id' => $org->id, 'role_id' => $adminRole->id])->save();

        Sanctum::actingAs($staff);
        $me = $this->getJson('/api/auth/me');
        $me->assertOk();
        $me->assertJsonPath('role', 'company');
        $me->assertJsonPath('activeOrganization.type', 'municipal_hall');
        $me->assertJsonPath('verificationStatus', 'approved');
    }
}
