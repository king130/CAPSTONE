<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Company;
use App\Models\Internship;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Permission;
use App\Models\Role;
use App\Models\School;
use App\Models\SchoolCourseRequirement;
use App\Models\Student;
use App\Models\Subscription;
use App\Models\User;
use App\Services\ApplicationRequiredOjtHoursBackfill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SchoolCourseRequirementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param array<int, string> $permissionKeys
     * @param array<string, mixed> $settings
     * @return array{user: User, organization: Organization, school: School}
     */
    private function createSchool(array $permissionKeys, array $settings = []): array
    {
        foreach (array_unique(array_merge($permissionKeys, [
            'org.manage_students',
            'manage_users',
            'org.manage_members',
            'manage_profile',
            'org.manage_profile',
        ])) as $key) {
            Permission::query()->updateOrCreate(
                ['key' => $key],
                ['scope' => 'organization', 'description' => $key]
            );
        }

        $user = User::factory()->create([
            'role' => 'school',
            'email' => 'school.'.uniqid('', true).'@example.com',
            'is_active' => true,
        ]);
        $subscription = Subscription::query()->create([
            'plan' => 'standard',
            'status' => 'active',
            'billing_cycle' => 'monthly',
        ]);
        $organization = Organization::query()->create([
            'name' => 'Course Hours School '.uniqid(),
            'type' => 'school',
            'subscription_id' => $subscription->id,
            'owner_user_id' => $user->id,
            'settings' => $settings,
            'is_active' => true,
        ]);
        $role = Role::query()->create([
            'tenant_id' => $organization->id,
            'name' => 'School Admin',
            'slug' => 'school_admin_'.uniqid(),
            'scope' => 'organization',
            'organization_type' => 'school',
            'is_system' => false,
        ]);
        $role->permissions()->sync(Permission::query()->whereIn('key', $permissionKeys)->pluck('id')->all());
        OrganizationMembership::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
            'status' => 'active',
            'title' => 'Admin',
        ]);
        $user->forceFill([
            'tenant_id' => $organization->id,
            'role_id' => $role->id,
        ])->save();
        $school = School::query()->create([
            'user_id' => $user->id,
            'organization_id' => $organization->id,
            'institution_name' => $organization->name,
            'subscription_code' => 'CRS-'.strtoupper(substr(uniqid(), -4)),
            'verification_status' => 'approved',
        ]);

        return compact('user', 'organization', 'school');
    }

    private function attachStaff(array $schoolContext, array $permissionKeys, string $name): User
    {
        $role = Role::query()->create([
            'tenant_id' => $schoolContext['organization']->id,
            'name' => $name,
            'slug' => strtolower(str_replace(' ', '_', $name)).'_'.uniqid(),
            'scope' => 'organization',
            'organization_type' => 'school',
            'is_system' => false,
        ]);
        $role->permissions()->sync(Permission::query()->whereIn('key', $permissionKeys)->pluck('id')->all());
        $user = User::factory()->create([
            'role' => 'school',
            'email' => strtolower(str_replace(' ', '.', $name)).'.'.uniqid('', true).'@example.com',
            'is_active' => true,
            'tenant_id' => $schoolContext['organization']->id,
            'role_id' => $role->id,
        ]);
        OrganizationMembership::query()->create([
            'organization_id' => $schoolContext['organization']->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
            'status' => 'active',
            'title' => $name,
        ]);

        return $user;
    }

    public function test_school_admin_can_create_update_and_list_course_requirements(): void
    {
        $school = $this->createSchool(['org.manage_students', 'manage_users']);
        Sanctum::actingAs($school['user']);

        $created = $this->postJson('/api/school-course-requirements', [
            'courseName' => '  BS Information Technology  ',
            'requiredHours' => 500,
        ]);
        $created->assertCreated();
        $created->assertJsonPath('data.courseName', 'BS Information Technology');
        $created->assertJsonPath('data.requiredHours', 500);
        $created->assertJsonPath('data.schoolId', (string) $school['school']->id);

        $id = $created->json('data.id');
        $updated = $this->patchJson('/api/school-course-requirements/'.$id, [
            'courseName' => 'BS Information Technology',
            'requiredHours' => 486,
        ]);
        $updated->assertOk();
        $updated->assertJsonPath('data.requiredHours', 486);

        $this->getJson('/api/school-course-requirements')
            ->assertOk()
            ->assertJsonPath('data.0.courseName', 'BS Information Technology')
            ->assertJsonPath('data.0.requiredHours', 486);

        $this->assertSame(1, $school['school']->courseRequirements()->count());
    }

    public function test_duplicate_school_and_course_is_rejected_case_insensitively(): void
    {
        $school = $this->createSchool(['org.manage_students']);
        Sanctum::actingAs($school['user']);

        $this->postJson('/api/school-course-requirements', [
            'courseName' => 'BS Information Technology',
            'requiredHours' => 500,
        ])->assertCreated();

        $duplicate = $this->postJson('/api/school-course-requirements', [
            'courseName' => 'bs   information technology',
            'requiredHours' => 600,
        ]);
        $duplicate->assertStatus(422);
        $duplicate->assertJsonPath('code', 'duplicate_course_requirement');
        $this->assertSame(1, SchoolCourseRequirement::query()->count());
    }

    public function test_same_course_name_can_exist_for_different_schools(): void
    {
        $first = $this->createSchool(['org.manage_students']);
        $second = $this->createSchool(['org.manage_students']);

        Sanctum::actingAs($first['user']);
        $this->postJson('/api/school-course-requirements', [
            'courseName' => 'BS Information Technology',
            'requiredHours' => 500,
        ])->assertCreated();

        Sanctum::actingAs($second['user']);
        $this->postJson('/api/school-course-requirements', [
            'courseName' => 'BS Information Technology',
            'requiredHours' => 300,
        ])->assertCreated();

        $this->assertSame(500, (int) $first['school']->courseRequirements()->first()?->required_hours);
        $this->assertSame(300, (int) $second['school']->courseRequirements()->first()?->required_hours);
    }

    public function test_non_positive_required_hours_are_rejected(): void
    {
        $school = $this->createSchool(['org.manage_students']);
        Sanctum::actingAs($school['user']);

        $this->postJson('/api/school-course-requirements', [
            'courseName' => 'BS Computer Science',
            'requiredHours' => 0,
        ])->assertStatus(422);

        $this->postJson('/api/school-course-requirements', [
            'courseName' => 'BS Computer Science',
            'requiredHours' => -10,
        ])->assertStatus(422);

        $this->assertSame(0, SchoolCourseRequirement::query()->count());
    }

    public function test_teacher_with_only_manage_profile_cannot_modify_requirements(): void
    {
        $school = $this->createSchool(['org.manage_students']);
        $teacher = $this->attachStaff($school, ['manage_profile', 'org.manage_profile'], 'Teacher');

        Sanctum::actingAs($teacher);
        $this->postJson('/api/school-course-requirements', [
            'courseName' => 'BS Information Technology',
            'requiredHours' => 500,
        ])->assertForbidden();
        $this->getJson('/api/school-course-requirements')->assertForbidden();
    }

    public function test_school_cannot_update_another_schools_requirement(): void
    {
        $first = $this->createSchool(['org.manage_students']);
        $second = $this->createSchool(['org.manage_students']);

        Sanctum::actingAs($first['user']);
        $created = $this->postJson('/api/school-course-requirements', [
            'courseName' => 'BS Information Technology',
            'requiredHours' => 500,
        ])->assertCreated();

        Sanctum::actingAs($second['user']);
        $this->patchJson('/api/school-course-requirements/'.$created->json('data.id'), [
            'courseName' => 'BS Information Technology',
            'requiredHours' => 100,
        ])->assertNotFound();

        $this->assertSame(500, (int) SchoolCourseRequirement::query()->find($created->json('data.id'))?->required_hours);
    }

    public function test_legacy_application_keeps_null_required_hours_and_backfill_uses_only_configured_org_value(): void
    {
        $withoutSetting = $this->createSchool(['org.manage_students'], []);
        $withSetting = $this->createSchool(['org.manage_students'], ['required_ojt_hours' => 486]);

        $legacy = $this->makeApplication($withoutSetting);
        $configured = $this->makeApplication($withSetting);

        $this->assertNull($legacy->required_ojt_hours);
        $this->assertNull($configured->fresh()->required_ojt_hours);

        $updated = app(ApplicationRequiredOjtHoursBackfill::class)->backfill();
        $this->assertSame(1, $updated);
        $this->assertNull($legacy->fresh()->required_ojt_hours);
        $this->assertSame(486, (int) $configured->fresh()->required_ojt_hours);

        $legacy->forceFill(['notes' => 'still valid'])->save();
        $this->assertNull($legacy->fresh()->required_ojt_hours);
        $this->assertSame('still valid', $legacy->fresh()->notes);
    }

    /**
     * @param array{school: School, organization: Organization} $schoolContext
     */
    private function makeApplication(array $schoolContext): Application
    {
        $companyUser = User::factory()->create([
            'role' => 'company',
            'email' => 'company.'.uniqid('', true).'@example.com',
        ]);
        $company = Company::query()->create([
            'user_id' => $companyUser->id,
            'company_name' => 'Hours Company',
            'verification_status' => 'approved',
        ]);
        $internship = Internship::query()->create([
            'company_id' => $company->id,
            'host_type' => 'company',
            'title' => 'Existing Internship',
            'duration' => '486 hours',
            'status' => 'active',
        ]);
        $studentUser = User::factory()->create([
            'role' => 'student',
            'email' => 'student.'.uniqid('', true).'@example.com',
        ]);
        $student = Student::query()->create([
            'user_id' => $studentUser->id,
            'school_id' => $schoolContext['school']->id,
            'organization_id' => $schoolContext['organization']->id,
            'school_name' => $schoolContext['school']->institution_name,
            'course' => 'BS Computer Science',
        ]);

        return Application::query()->create([
            'internship_id' => $internship->id,
            'student_id' => $student->id,
            'company_id' => $company->id,
            'status' => 'accepted',
            'internship_title' => $internship->title,
            'student_name' => $studentUser->name,
            'student_email' => $studentUser->email,
            'student_course' => $student->course,
        ]);
    }
}
