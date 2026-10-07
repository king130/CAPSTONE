<?php

namespace Tests\Feature;

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

class OrganizationPublicProfileTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{organization: Organization, company: Company, companyUser: User}
     */
    private function createCompanyOrg(bool $active = true, string $verification = 'approved'): array
    {
        $companyUser = User::factory()->create([
            'role' => 'company',
            'email' => 'pubco.'.uniqid('', true).'@example.com',
            'is_active' => true,
        ]);
        $sub = Subscription::query()->create(['plan' => 'free', 'status' => 'active', 'billing_cycle' => 'monthly']);
        $organization = Organization::query()->create([
            'name' => 'Public Co',
            'type' => 'company',
            'subscription_id' => $sub->id,
            'owner_user_id' => $companyUser->id,
            'is_active' => $active,
            'tagline' => 'Hire smarter',
            'description' => 'A public description',
            'address' => '123 Main St',
            'city' => 'Bacoor City',
            'website' => 'https://example.com',
            'industry' => 'Technology',
            'perks' => ['Mentorship'],
            'settings' => ['secret' => 'should-not-leak'],
        ]);
        $companyUser->forceFill(['tenant_id' => $organization->id])->save();
        $company = Company::query()->create([
            'user_id' => $companyUser->id,
            'organization_id' => $organization->id,
            'company_name' => 'Public Co',
            'company_email' => 'private@example.com',
            'verification_status' => $verification,
        ]);

        return compact('organization', 'company', 'companyUser');
    }

    public function test_public_org_returns_safe_fields_only(): void
    {
        $ctx = $this->createCompanyOrg();

        $response = $this->getJson('/api/organizations/'.$ctx['organization']->id);
        $response->assertOk();
        $response->assertJsonPath('data.id', (string) $ctx['organization']->id);
        $response->assertJsonPath('data.name', 'Public Co');
        $response->assertJsonPath('data.type', 'company');
        $response->assertJsonPath('data.verified', true);
        $response->assertJsonPath('data.tagline', 'Hire smarter');
        $response->assertJsonPath('data.city', 'Bacoor City');
        $response->assertJsonPath('data.website', 'https://example.com');
        $response->assertJsonPath('data.perks.0', 'Mentorship');

        $json = $response->json('data');
        foreach ([
            'email',
            'owner_user_id',
            'ownerUserId',
            'subscription_id',
            'subscriptionId',
            'settings',
            'memberships',
            'company_email',
            'companyEmail',
            'photoLimit',
            'photo_limit',
        ] as $forbidden) {
            $this->assertArrayNotHasKey($forbidden, $json);
        }

        $encoded = json_encode($json);
        $this->assertStringNotContainsString('private@example.com', (string) $encoded);
        $this->assertStringNotContainsString('should-not-leak', (string) $encoded);
    }

    public function test_inactive_or_unknown_organization_returns_404(): void
    {
        $inactive = $this->createCompanyOrg(false);
        $this->getJson('/api/organizations/'.$inactive['organization']->id)->assertNotFound();
        $this->getJson('/api/organizations/999999')->assertNotFound();
    }

    public function test_company_internships_endpoint_is_paginated(): void
    {
        $ctx = $this->createCompanyOrg();
        Internship::query()->create([
            'company_id' => $ctx['company']->id,
            'company_name' => 'Public Co',
            'host_type' => 'company',
            'host_name' => 'Public Co',
            'title' => 'Backend Intern',
            'status' => 'active',
            'slots_available' => 2,
            'location' => 'Bacoor City',
            'work_setup' => 'hybrid',
        ]);

        $response = $this->getJson('/api/organizations/'.$ctx['organization']->id.'/internships?page=1&per_page=10');
        $response->assertOk();
        $response->assertJsonPath('meta.total', 1);
        $response->assertJsonPath('data.0.title', 'Backend Intern');
        $response->assertJsonPath('data.0.organizationId', (string) $ctx['organization']->id);
    }

    public function test_internship_detail_partnered_flag_for_authenticated_student(): void
    {
        $ctx = $this->createCompanyOrg();

        $schoolUser = User::factory()->create([
            'role' => 'school',
            'email' => 'pubschool.'.uniqid('', true).'@example.com',
            'is_active' => true,
        ]);
        $schoolSub = Subscription::query()->create(['plan' => 'free', 'status' => 'active', 'billing_cycle' => 'monthly']);
        $schoolOrg = Organization::query()->create([
            'name' => 'Partner School',
            'type' => 'school',
            'subscription_id' => $schoolSub->id,
            'owner_user_id' => $schoolUser->id,
            'is_active' => true,
        ]);
        $schoolUser->forceFill(['tenant_id' => $schoolOrg->id])->save();
        $school = School::query()->create([
            'user_id' => $schoolUser->id,
            'organization_id' => $schoolOrg->id,
            'institution_name' => 'Partner School',
            'subscription_code' => 'PS-'.strtoupper(substr(uniqid(), -4)),
            'verification_status' => 'approved',
        ]);

        $studentUser = User::factory()->create([
            'role' => 'student',
            'email' => 'pubstudent.'.uniqid('', true).'@example.com',
            'is_active' => true,
        ]);
        Student::query()->create([
            'user_id' => $studentUser->id,
            'school_id' => $school->id,
            'organization_id' => $schoolOrg->id,
            'school_name' => $school->institution_name,
            'school_subscription_code' => $school->subscription_code,
            'course' => 'BS Information Technology',
        ]);

        Contract::query()->create([
            'school_user_id' => $schoolUser->id,
            'school_name' => $school->institution_name,
            'company_user_id' => $ctx['companyUser']->id,
            'company_name' => $ctx['company']->company_name,
            'requested_by_role' => 'school',
            'requested_by_user_id' => $schoolUser->id,
            'partner_user_id' => $ctx['companyUser']->id,
            'status' => 'active',
            'subject' => 'Active MOA',
        ]);

        $internship = Internship::query()->create([
            'company_id' => $ctx['company']->id,
            'company_name' => 'Public Co',
            'host_type' => 'company',
            'host_name' => 'Public Co',
            'title' => 'Detail Intern',
            'status' => 'active',
            'slots_available' => 3,
            'location' => 'Bacoor City',
            'work_setup' => 'onsite',
            'perks' => ['Lunch'],
            'application_deadline' => now()->addMonth()->toDateString(),
            'start_date' => now()->addMonths(2)->toDateString(),
        ]);

        Sanctum::actingAs($studentUser);
        $response = $this->getJson('/api/internships/'.$internship->id);
        $response->assertOk();
        $response->assertJsonPath('data.partnered_with_my_school', true);
        $response->assertJsonPath('data.partneredWithMySchool', true);
        $response->assertJsonPath('data.verified', true);
        $response->assertJsonPath('data.work_setup', 'onsite');
        $response->assertJsonPath('data.slots_remaining', 3);
        $this->assertIsArray($response->json('data.organization_media'));
        $this->assertIsArray($response->json('data.organizationMedia'));
    }
}
