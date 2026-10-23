<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Internship;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Permission;
use App\Models\Role;
use App\Models\School;
use App\Models\Student;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApplicationDuplicateGuardTest extends TestCase
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
     *   internship: Internship,
     *   studentUser: User,
     *   student: Student
     * }
     */
    private function createContext(): array
    {
        foreach ([
            'org.approve_applications',
            'org.manage_applications',
            'org.view_applications',
            'org.manage_assessments',
        ] as $key) {
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
        $schoolSub = Subscription::query()->create([
            'plan' => 'standard',
            'status' => 'active',
            'billing_cycle' => 'monthly',
        ]);
        $schoolOrg = Organization::query()->create([
            'name' => 'Dup App School Org',
            'type' => 'school',
            'subscription_id' => $schoolSub->id,
            'owner_user_id' => $schoolUser->id,
            'settings' => [],
            'is_active' => true,
        ]);
        $schoolRole = Role::query()->create([
            'tenant_id' => $schoolOrg->id,
            'name' => 'School Admin',
            'slug' => 'school_admin_'.uniqid(),
            'scope' => 'organization',
            'organization_type' => 'school',
        ]);
        $schoolRole->permissions()->sync(
            Permission::query()->whereIn('key', [
                'org.approve_applications',
                'org.manage_applications',
                'org.view_applications',
                'org.manage_assessments',
            ])->pluck('id')
        );
        OrganizationMembership::query()->create([
            'organization_id' => $schoolOrg->id,
            'user_id' => $schoolUser->id,
            'role_id' => $schoolRole->id,
            'status' => 'active',
            'title' => 'Admin',
        ]);
        $schoolUser->forceFill([
            'tenant_id' => $schoolOrg->id,
            'role_id' => $schoolRole->id,
        ])->save();
        $school = School::query()->create([
            'user_id' => $schoolUser->id,
            'organization_id' => $schoolOrg->id,
            'institution_name' => 'Dup App School',
            'subscription_code' => 'DAS-'.strtoupper(substr(uniqid(), -4)),
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
            'name' => 'Dup App Company Org',
            'type' => 'company',
            'subscription_id' => $companySub->id,
            'owner_user_id' => $companyUser->id,
            'settings' => [],
            'is_active' => true,
        ]);
        $companyRole = Role::query()->create([
            'tenant_id' => $companyOrg->id,
            'name' => 'Company Admin',
            'slug' => 'company_admin_'.uniqid(),
            'scope' => 'organization',
            'organization_type' => 'company',
        ]);
        $companyRole->permissions()->sync(
            Permission::query()->whereIn('key', [
                'org.approve_applications',
                'org.manage_applications',
                'org.view_applications',
                'org.manage_assessments',
            ])->pluck('id')
        );
        OrganizationMembership::query()->create([
            'organization_id' => $companyOrg->id,
            'user_id' => $companyUser->id,
            'role_id' => $companyRole->id,
            'status' => 'active',
            'title' => 'Admin',
        ]);
        $companyUser->forceFill([
            'tenant_id' => $companyOrg->id,
            'role_id' => $companyRole->id,
        ])->save();
        $company = Company::query()->create([
            'user_id' => $companyUser->id,
            'organization_id' => $companyOrg->id,
            'company_name' => 'Dup App Company',
            'verification_status' => 'approved',
        ]);

        Contract::query()->create([
            'school_user_id' => $schoolUser->id,
            'school_name' => $school->institution_name,
            'company_user_id' => $companyUser->id,
            'company_name' => $company->company_name,
            'requested_by_role' => 'school',
            'requested_by_user_id' => $schoolUser->id,
            'partner_user_id' => $companyUser->id,
            'status' => 'active',
            'subject' => 'Dup App MOA',
        ]);

        $internship = Internship::query()->create([
            'company_id' => $company->id,
            'host_type' => 'company',
            'requires_resume' => false,
            'title' => 'Dup App Internship',
            'status' => 'active',
        ]);

        $studentUser = User::factory()->create([
            'role' => 'student',
            'email' => 'student.'.uniqid('', true).'@example.com',
            'name' => 'Dup App Student',
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
            'internship',
            'studentUser',
            'student'
        );
    }

    private function createSecondStudent(array $ctx): array
    {
        $studentUser = User::factory()->create([
            'role' => 'student',
            'email' => 'student2.'.uniqid('', true).'@example.com',
            'name' => 'Dup App Student Two',
            'is_active' => true,
        ]);
        $student = Student::query()->create([
            'user_id' => $studentUser->id,
            'school_id' => $ctx['school']->id,
            'organization_id' => $ctx['schoolOrg']->id,
            'school_name' => $ctx['school']->institution_name,
            'school_subscription_code' => $ctx['school']->subscription_code,
            'course' => 'BSCS',
        ]);

        return compact('studentUser', 'student');
    }

    public function test_first_application_succeeds_and_duplicate_is_rejected(): void
    {
        $ctx = $this->createContext();
        Sanctum::actingAs($ctx['studentUser']);

        $first = $this->postApplication($ctx['internship']->id);
        $first->assertCreated();
        $first->assertJsonPath('data.status', 'submitted');
        $this->assertSame(1, Application::query()->where([
            'student_id' => $ctx['student']->id,
            'internship_id' => $ctx['internship']->id,
        ])->count());

        $second = $this->postApplication($ctx['internship']->id, [
            'cover_letter' => 'Trying again',
        ]);
        $second->assertStatus(422);
        $second->assertJsonPath('code', 'duplicate_application');
        $second->assertJsonPath('errors.internship_id.0', 'You have already applied to this internship.');
        $this->assertSame(1, Application::query()->where([
            'student_id' => $ctx['student']->id,
            'internship_id' => $ctx['internship']->id,
        ])->count());
    }

    public function test_same_student_can_apply_to_different_internship(): void
    {
        $ctx = $this->createContext();
        $otherInternship = Internship::query()->create([
            'company_id' => $ctx['company']->id,
            'host_type' => 'company',
            'requires_resume' => false,
            'title' => 'Second Dup App Internship',
            'status' => 'active',
        ]);

        Sanctum::actingAs($ctx['studentUser']);
        $this->postApplication($ctx['internship']->id)->assertCreated();

        $response = $this->postApplication($otherInternship->id);
        $response->assertCreated();
        $this->assertSame(2, Application::query()->where('student_id', $ctx['student']->id)->count());
    }

    public function test_different_student_can_apply_to_same_internship(): void
    {
        $ctx = $this->createContext();
        $other = $this->createSecondStudent($ctx);

        Sanctum::actingAs($ctx['studentUser']);
        $this->postApplication($ctx['internship']->id)->assertCreated();

        Sanctum::actingAs($other['studentUser']);
        $response = $this->postApplication($ctx['internship']->id, [], $other['studentUser']);
        $response->assertCreated();
        $this->assertSame(2, Application::query()->where('internship_id', $ctx['internship']->id)->count());
    }

    public function test_duplicate_blocked_regardless_of_existing_application_status(): void
    {
        $ctx = $this->createContext();

        Application::query()->create([
            'internship_id' => $ctx['internship']->id,
            'student_id' => $ctx['student']->id,
            'company_id' => $ctx['company']->id,
            'status' => 'rejected',
            'internship_title' => $ctx['internship']->title,
            'student_name' => $ctx['studentUser']->name,
            'student_email' => $ctx['studentUser']->email,
        ]);

        Sanctum::actingAs($ctx['studentUser']);
        $response = $this->postApplication($ctx['internship']->id);

        $response->assertStatus(422);
        $response->assertJsonPath('code', 'duplicate_application');
        $this->assertSame(1, Application::query()->where([
            'student_id' => $ctx['student']->id,
            'internship_id' => $ctx['internship']->id,
        ])->count());
    }

    public function test_endorsement_and_acceptance_still_work_after_unique_application(): void
    {
        $ctx = $this->createContext();

        Sanctum::actingAs($ctx['studentUser']);
        $created = $this->postApplication($ctx['internship']->id);
        $created->assertCreated();
        $applicationId = (string) $created->json('data.id');

        Sanctum::actingAs($ctx['schoolUser']);
        $this->patchJson('/api/applications/'.$applicationId.'/status', [
            'status' => 'endorsed',
        ])->assertOk()->assertJsonPath('data.status', 'endorsed');

        Sanctum::actingAs($ctx['companyUser']);
        $interview = $this->postJson('/api/interviews', [
            'application_id' => $applicationId,
            'scheduled_at' => now()->addDay()->toIso8601String(),
            'mode' => 'online',
        ]);
        $interview->assertCreated();

        Sanctum::actingAs($ctx['studentUser']);
        $this->patchJson('/api/interviews/'.$interview->json('data.id').'/confirm')->assertOk();

        Sanctum::actingAs($ctx['companyUser']);
        $this->postJson('/api/applications/'.$applicationId.'/tracker/transition', [
            'to_stage' => 'interviewed',
        ])->assertOk();
        $this->postJson('/api/applications/'.$applicationId.'/tracker/assessment', [
            'technical' => 4,
            'communication' => 4,
            'attitude' => 4,
        ])->assertCreated();
        $this->patchJson('/api/applications/'.$applicationId.'/status', [
            'status' => 'accepted',
        ])->assertOk()->assertJsonPath('data.status', 'accepted');
    }
}
