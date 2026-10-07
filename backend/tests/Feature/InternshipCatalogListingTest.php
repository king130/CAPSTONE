<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Internship;
use App\Models\Organization;
use App\Models\OrganizationMedia;
use App\Models\OrganizationMembership;
use App\Models\Permission;
use App\Models\Role;
use App\Models\School;
use App\Models\Student;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InternshipCatalogListingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{
     *   schoolUser: User,
     *   school: School,
     *   companyUser: User,
     *   company: Company,
     *   companyOrg: Organization,
     *   studentUser: User,
     *   student: Student
     * }
     */
    private function createContext(): array
    {
        foreach (['org.manage_profile', 'org.manage_internships'] as $key) {
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
        $schoolSub = Subscription::query()->create(['plan' => 'free', 'status' => 'active', 'billing_cycle' => 'monthly']);
        $schoolOrg = Organization::query()->create([
            'name' => 'Catalog School',
            'type' => 'school',
            'subscription_id' => $schoolSub->id,
            'owner_user_id' => $schoolUser->id,
            'is_active' => true,
            'city' => 'Imus City',
        ]);
        $schoolRole = Role::query()->create([
            'tenant_id' => $schoolOrg->id,
            'name' => 'School Admin',
            'slug' => 'school_admin_'.uniqid(),
            'scope' => 'organization',
            'organization_type' => 'school',
        ]);
        $schoolRole->permissions()->sync(Permission::query()->pluck('id'));
        OrganizationMembership::query()->create([
            'organization_id' => $schoolOrg->id,
            'user_id' => $schoolUser->id,
            'role_id' => $schoolRole->id,
            'status' => 'active',
            'title' => 'Admin',
        ]);
        $schoolUser->forceFill(['tenant_id' => $schoolOrg->id, 'role_id' => $schoolRole->id])->save();
        $school = School::query()->create([
            'user_id' => $schoolUser->id,
            'organization_id' => $schoolOrg->id,
            'institution_name' => 'Catalog School',
            'subscription_code' => 'CAT-'.strtoupper(substr(uniqid(), -4)),
            'verification_status' => 'approved',
        ]);

        $companyUser = User::factory()->create([
            'role' => 'company',
            'email' => 'company.'.uniqid('', true).'@example.com',
            'is_active' => true,
        ]);
        $companySub = Subscription::query()->create(['plan' => 'free', 'status' => 'active', 'billing_cycle' => 'monthly']);
        $companyOrg = Organization::query()->create([
            'name' => 'Catalog Company',
            'type' => 'company',
            'subscription_id' => $companySub->id,
            'owner_user_id' => $companyUser->id,
            'is_active' => true,
            'city' => 'Bacoor City',
        ]);
        $companyRole = Role::query()->create([
            'tenant_id' => $companyOrg->id,
            'name' => 'Company Admin',
            'slug' => 'company_admin_'.uniqid(),
            'scope' => 'organization',
            'organization_type' => 'company',
        ]);
        $companyRole->permissions()->sync(Permission::query()->pluck('id'));
        OrganizationMembership::query()->create([
            'organization_id' => $companyOrg->id,
            'user_id' => $companyUser->id,
            'role_id' => $companyRole->id,
            'status' => 'active',
            'title' => 'Admin',
        ]);
        $companyUser->forceFill(['tenant_id' => $companyOrg->id, 'role_id' => $companyRole->id])->save();
        $company = Company::query()->create([
            'user_id' => $companyUser->id,
            'organization_id' => $companyOrg->id,
            'company_name' => 'Catalog Company',
            'verification_status' => 'approved',
        ]);

        $studentUser = User::factory()->create([
            'role' => 'student',
            'email' => 'student.'.uniqid('', true).'@example.com',
            'name' => 'Catalog Student',
            'is_active' => true,
        ]);
        $student = Student::query()->create([
            'user_id' => $studentUser->id,
            'school_id' => $school->id,
            'organization_id' => $schoolOrg->id,
            'school_name' => $school->institution_name,
            'school_subscription_code' => $school->subscription_code,
            'course' => 'BS Information Technology',
        ]);

        return compact('schoolUser', 'school', 'companyUser', 'company', 'companyOrg', 'studentUser', 'student');
    }

    private function makeInternship(array $ctx, array $overrides = []): Internship
    {
        return Internship::query()->create(array_merge([
            'company_id' => $ctx['company']->id,
            'company_name' => $ctx['company']->company_name,
            'host_type' => 'company',
            'host_name' => $ctx['company']->company_name,
            'title' => 'Frontend Intern',
            'description' => 'Build Vue apps in Cavite',
            'location' => 'Bacoor City, Cavite',
            'city' => 'Bacoor City',
            'work_setup' => 'hybrid',
            'type' => 'Hybrid',
            'duration' => '486 hours',
            'slots_available' => 2,
            'status' => 'active',
            'eligible_courses' => ['BS Information Technology'],
            'allowance' => '3000',
            'schedule_type' => 'fixed',
            'approval_status' => 'approved',
        ], $overrides));
    }

    public function test_filters_sort_pagination_and_legacy_shape(): void
    {
        Storage::fake('public');
        $ctx = $this->createContext();
        $a = $this->makeInternship($ctx, ['title' => 'Alpha Role', 'city' => 'Bacoor City', 'allowance' => '5000', 'work_setup' => 'onsite']);
        $b = $this->makeInternship($ctx, [
            'title' => 'Beta Role',
            'city' => 'Imus City',
            'location' => 'Imus City, Cavite',
            'allowance' => '1000',
            'work_setup' => 'remote',
            'eligible_courses' => ['BS Computer Science'],
        ]);

        OrganizationMedia::query()->create([
            'organization_id' => $ctx['companyOrg']->id,
            'path' => 'organization-media/'.$ctx['companyOrg']->id.'/cover.jpg',
            'caption' => 'Cover',
            'sort_order' => 0,
            'is_cover' => true,
        ]);
        Storage::disk('public')->put('organization-media/'.$ctx['companyOrg']->id.'/cover.jpg', 'fake');

        $legacy = $this->getJson('/api/internships?status=active&city=Bacoor');
        $legacy->assertOk();
        $this->assertIsArray($legacy->json('data'));
        $this->assertArrayNotHasKey('meta', $legacy->json());
        $legacy->assertJsonPath('data.0.city', 'Bacoor City');
        $legacy->assertJsonPath('data.0.verified', true);
        $this->assertNotNull($legacy->json('data.0.cover_image'));
        $this->assertSame(1, count($legacy->json('data')));

        $sorted = $this->getJson('/api/internships?status=active&sort=title');
        $sorted->assertOk();
        $this->assertSame('Alpha Role', $sorted->json('data.0.title'));
        $this->assertSame('Beta Role', $sorted->json('data.1.title'));

        $paged = $this->getJson('/api/internships?status=active&page=1&per_page=1&sort=title');
        $paged->assertOk();
        $paged->assertJsonPath('meta.per_page', 1);
        $paged->assertJsonPath('meta.total', 2);
        $this->assertCount(1, $paged->json('data'));

        $this->assertNotNull($a->id);
        $this->assertNotNull($b->id);
    }

    public function test_partnered_with_my_school_for_active_pending_and_cancelled_agreements(): void
    {
        $ctx = $this->createContext();
        $internship = $this->makeInternship($ctx);

        Sanctum::actingAs($ctx['studentUser']);
        $this->getJson('/api/internships?status=active')
            ->assertOk()
            ->assertJsonPath('data.0.partnered_with_my_school', false);

        Contract::query()->create([
            'school_user_id' => $ctx['schoolUser']->id,
            'school_name' => $ctx['school']->institution_name,
            'company_user_id' => $ctx['companyUser']->id,
            'company_name' => $ctx['company']->company_name,
            'requested_by_role' => 'school',
            'requested_by_user_id' => $ctx['schoolUser']->id,
            'partner_user_id' => $ctx['companyUser']->id,
            'status' => 'pending',
            'subject' => 'Pending MOA',
        ]);
        $this->getJson('/api/internships?status=active')
            ->assertOk()
            ->assertJsonPath('data.0.partnered_with_my_school', false);

        Contract::query()->where('status', 'pending')->update(['status' => 'cancelled']);
        $this->getJson('/api/internships?status=active')
            ->assertOk()
            ->assertJsonPath('data.0.partnered_with_my_school', false);

        Contract::query()->create([
            'school_user_id' => $ctx['schoolUser']->id,
            'school_name' => $ctx['school']->institution_name,
            'company_user_id' => $ctx['companyUser']->id,
            'company_name' => $ctx['company']->company_name,
            'requested_by_role' => 'school',
            'requested_by_user_id' => $ctx['schoolUser']->id,
            'partner_user_id' => $ctx['companyUser']->id,
            'status' => 'active',
            'subject' => 'Active MOA',
        ]);
        $this->getJson('/api/internships?status=active')
            ->assertOk()
            ->assertJsonPath('data.0.partnered_with_my_school', true);

        $schoolHosted = Internship::query()->create([
            'school_id' => $ctx['school']->id,
            'host_type' => 'school',
            'host_name' => $ctx['school']->institution_name,
            'title' => 'School Lab Intern',
            'location' => 'Imus City',
            'city' => 'Imus City',
            'work_setup' => 'onsite',
            'slots_available' => 1,
            'status' => 'active',
            'approval_status' => 'approved',
        ]);
        $this->assertNotNull($schoolHosted->id);
        $response = $this->getJson('/api/internships?status=active&q=School Lab');
        $response->assertOk();
        $response->assertJsonPath('data.0.partnered_with_my_school', true);
    }

    public function test_slots_remaining_never_negative(): void
    {
        $ctx = $this->createContext();
        $internship = $this->makeInternship($ctx, ['slots_available' => 1]);

        Application::query()->create([
            'internship_id' => $internship->id,
            'student_id' => $ctx['student']->id,
            'company_id' => $ctx['company']->id,
            'status' => 'accepted',
            'internship_title' => $internship->title,
            'student_name' => $ctx['studentUser']->name,
            'student_email' => $ctx['studentUser']->email,
        ]);
        Application::query()->create([
            'internship_id' => $internship->id,
            'student_id' => $ctx['student']->id,
            'company_id' => $ctx['company']->id,
            'status' => 'accepted',
            'internship_title' => $internship->title,
            'student_name' => $ctx['studentUser']->name,
            'student_email' => $ctx['studentUser']->email,
        ]);

        $response = $this->getJson('/api/internships?status=active');
        $response->assertOk();
        $response->assertJsonPath('data.0.slots_remaining', 0);
        $this->assertGreaterThanOrEqual(0, (int) $response->json('data.0.slots_remaining'));
    }
}
