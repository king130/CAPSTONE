<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Internship;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SavedInternship;
use App\Models\School;
use App\Models\Student;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SavedInternshipTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{
     *   studentUser: User,
     *   otherStudent: User,
     *   companyUser: User,
     *   company: Company,
     *   activeInternship: Internship,
     *   draftInternship: Internship
     * }
     */
    private function createContext(): array
    {
        Permission::query()->updateOrCreate(
            ['key' => 'org.manage_internships'],
            ['scope' => 'organization', 'description' => 'org.manage_internships']
        );

        $schoolUser = User::factory()->create([
            'role' => 'school',
            'email' => 'save-school.'.uniqid('', true).'@example.com',
            'is_active' => true,
        ]);
        $schoolSub = Subscription::query()->create(['plan' => 'free', 'status' => 'active', 'billing_cycle' => 'monthly']);
        $schoolOrg = Organization::query()->create([
            'name' => 'Save School',
            'type' => 'school',
            'subscription_id' => $schoolSub->id,
            'owner_user_id' => $schoolUser->id,
            'is_active' => true,
        ]);
        $schoolUser->forceFill(['tenant_id' => $schoolOrg->id])->save();
        $school = School::query()->create([
            'user_id' => $schoolUser->id,
            'organization_id' => $schoolOrg->id,
            'institution_name' => 'Save School',
            'subscription_code' => 'SV-'.strtoupper(substr(uniqid(), -4)),
            'verification_status' => 'approved',
        ]);

        $companyUser = User::factory()->create([
            'role' => 'company',
            'email' => 'save-company.'.uniqid('', true).'@example.com',
            'is_active' => true,
        ]);
        $companySub = Subscription::query()->create(['plan' => 'free', 'status' => 'active', 'billing_cycle' => 'monthly']);
        $companyOrg = Organization::query()->create([
            'name' => 'Save Company',
            'type' => 'company',
            'subscription_id' => $companySub->id,
            'owner_user_id' => $companyUser->id,
            'is_active' => true,
        ]);
        $role = Role::query()->create([
            'tenant_id' => $companyOrg->id,
            'name' => 'Company Admin',
            'slug' => 'company_admin_'.uniqid(),
            'scope' => 'organization',
            'organization_type' => 'company',
        ]);
        OrganizationMembership::query()->create([
            'organization_id' => $companyOrg->id,
            'user_id' => $companyUser->id,
            'role_id' => $role->id,
            'status' => 'active',
            'title' => 'Admin',
        ]);
        $companyUser->forceFill(['tenant_id' => $companyOrg->id, 'role_id' => $role->id])->save();
        $company = Company::query()->create([
            'user_id' => $companyUser->id,
            'organization_id' => $companyOrg->id,
            'company_name' => 'Save Company',
            'verification_status' => 'approved',
        ]);

        $studentUser = User::factory()->create([
            'role' => 'student',
            'email' => 'save-student.'.uniqid('', true).'@example.com',
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

        $otherStudent = User::factory()->create([
            'role' => 'student',
            'email' => 'save-other.'.uniqid('', true).'@example.com',
            'is_active' => true,
        ]);
        Student::query()->create([
            'user_id' => $otherStudent->id,
            'school_id' => $school->id,
            'organization_id' => $schoolOrg->id,
            'school_name' => $school->institution_name,
            'school_subscription_code' => $school->subscription_code,
            'course' => 'BS Computer Science',
        ]);

        $activeInternship = Internship::query()->create([
            'company_id' => $company->id,
            'company_name' => 'Save Company',
            'host_type' => 'company',
            'host_name' => 'Save Company',
            'title' => 'Active Saved Role',
            'status' => 'active',
            'slots_available' => 2,
            'location' => 'Bacoor City',
            'work_setup' => 'hybrid',
        ]);
        $draftInternship = Internship::query()->create([
            'company_id' => $company->id,
            'company_name' => 'Save Company',
            'host_type' => 'company',
            'host_name' => 'Save Company',
            'title' => 'Draft Role',
            'status' => 'draft',
            'slots_available' => 1,
            'location' => 'Imus City',
            'work_setup' => 'onsite',
        ]);

        return compact('studentUser', 'otherStudent', 'companyUser', 'company', 'activeInternship', 'draftInternship');
    }

    public function test_only_students_can_manage_saved_internships(): void
    {
        $ctx = $this->createContext();

        Sanctum::actingAs($ctx['companyUser']);
        $this->getJson('/api/saved-internships')->assertForbidden();
        $this->postJson('/api/saved-internships', ['internship_id' => $ctx['activeInternship']->id])->assertForbidden();
        $this->deleteJson('/api/saved-internships/'.$ctx['activeInternship']->id)->assertForbidden();
    }

    public function test_student_cannot_see_or_modify_another_users_saves(): void
    {
        $ctx = $this->createContext();
        SavedInternship::query()->create([
            'user_id' => $ctx['otherStudent']->id,
            'internship_id' => $ctx['activeInternship']->id,
        ]);

        Sanctum::actingAs($ctx['studentUser']);
        $this->getJson('/api/saved-internships')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->deleteJson('/api/saved-internships/'.$ctx['activeInternship']->id)->assertNoContent();
        $this->assertDatabaseHas('saved_internships', [
            'user_id' => $ctx['otherStudent']->id,
            'internship_id' => $ctx['activeInternship']->id,
        ]);
    }

    public function test_duplicate_post_is_idempotent_and_inactive_cannot_be_saved(): void
    {
        $ctx = $this->createContext();
        Sanctum::actingAs($ctx['studentUser']);

        $this->postJson('/api/saved-internships', ['internship_id' => $ctx['draftInternship']->id])
            ->assertStatus(422);

        $this->postJson('/api/saved-internships', ['internship_id' => $ctx['activeInternship']->id])
            ->assertCreated()
            ->assertJsonPath('data.saved', true);

        $this->postJson('/api/saved-internships', ['internship_id' => $ctx['activeInternship']->id])
            ->assertCreated()
            ->assertJsonPath('data.saved', true);

        $this->assertSame(1, SavedInternship::query()->where('user_id', $ctx['studentUser']->id)->count());

        $ctx['activeInternship']->update(['status' => 'closed']);
        $this->getJson('/api/saved-internships')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_catalog_saved_flag_has_no_n_plus_one(): void
    {
        $ctx = $this->createContext();
        for ($i = 0; $i < 4; $i++) {
            $internship = Internship::query()->create([
                'company_id' => $ctx['company']->id,
                'company_name' => 'Save Company',
                'host_type' => 'company',
                'host_name' => 'Save Company',
                'title' => 'Role '.$i,
                'status' => 'active',
                'slots_available' => 1,
                'location' => 'Bacoor City',
                'work_setup' => 'remote',
            ]);
            if ($i < 2) {
                SavedInternship::query()->create([
                    'user_id' => $ctx['studentUser']->id,
                    'internship_id' => $internship->id,
                ]);
            }
        }

        Sanctum::actingAs($ctx['studentUser']);
        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = $this->getJson('/api/internships?status=active');
        $response->assertOk();

        $savedQueries = collect(DB::getQueryLog())
            ->filter(fn (array $query) => str_contains(strtolower($query['query']), 'saved_internships'))
            ->count();

        $this->assertLessThanOrEqual(1, $savedQueries);
        $this->assertTrue(collect($response->json('data'))->contains(fn ($row) => ($row['saved'] ?? null) === true));
        $this->assertTrue(collect($response->json('data'))->contains(fn ($row) => ($row['saved'] ?? null) === false));
    }
}
