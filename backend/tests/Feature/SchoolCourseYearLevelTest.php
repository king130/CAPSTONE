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
use App\Models\SchoolCourseRequirement;
use App\Models\Student;
use App\Models\Subscription;
use App\Models\User;
use App\Services\RequiredOjtHoursAccessor;
use App\Support\StudentYearLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SchoolCourseYearLevelTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<int, string>  $permissionKeys
     * @return array{user: User, organization: Organization, school: School}
     */
    private function createSchool(array $permissionKeys = ['org.manage_students']): array
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
            'name' => 'Year Level School '.uniqid(),
            'type' => 'school',
            'subscription_id' => $subscription->id,
            'owner_user_id' => $user->id,
            'settings' => [],
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
            'subscription_code' => 'YL-'.strtoupper(substr(uniqid(), -4)),
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

    /**
     * @return array{internship: Internship, companyUser: User, studentUser: User, student: Student}
     */
    private function createApplyContext(array $schoolContext, string $hostType, string $yearLevel, string $course = 'BS Information Technology'): array
    {
        $hostUser = User::factory()->create([
            'role' => $hostType === 'municipal_hall' ? 'company' : 'company',
            'email' => 'host.'.uniqid('', true).'@example.com',
        ]);
        $company = Company::query()->create([
            'user_id' => $hostUser->id,
            'company_name' => $hostType === 'municipal_hall' ? 'Naic Municipal Hall' : 'Apply Co',
            'verification_status' => 'approved',
        ]);
        $internship = Internship::query()->create([
            'company_id' => $company->id,
            'host_type' => $hostType,
            'title' => 'OJT Role',
            'duration' => '486 hours',
            'status' => 'active',
            'required_documents' => ['Endorsement'],
            'requires_resume' => false,
        ]);

        Contract::query()->create([
            'school_user_id' => $schoolContext['user']->id,
            'school_name' => $schoolContext['school']->institution_name,
            'company_user_id' => $hostUser->id,
            'company_name' => $company->company_name,
            'requested_by_role' => 'school',
            'requested_by_user_id' => $schoolContext['user']->id,
            'partner_user_id' => $hostUser->id,
            'status' => 'active',
            'subject' => 'Phase B MOA',
        ]);

        $studentUser = User::factory()->create([
            'role' => 'student',
            'email' => 'student.'.uniqid('', true).'@example.com',
            'is_active' => true,
        ]);
        $student = Student::query()->create([
            'user_id' => $studentUser->id,
            'school_id' => $schoolContext['school']->id,
            'organization_id' => $schoolContext['organization']->id,
            'school_name' => $schoolContext['school']->institution_name,
            'course' => $course,
            'year_level' => $yearLevel,
        ]);

        return [
            'internship' => $internship,
            'companyUser' => $hostUser,
            'studentUser' => $studentUser,
            'student' => $student,
        ];
    }

    public function test_create_defaults_to_third_and_fourth_year(): void
    {
        $school = $this->createSchool();
        Sanctum::actingAs($school['user']);

        $created = $this->postJson('/api/school-course-requirements', [
            'courseName' => 'BS Information Technology',
            'requiredHours' => 500,
        ])->assertCreated();

        $created->assertJsonPath('data.requiredHours', 500);
        $created->assertJsonPath('data.eligibleYearLevels', StudentYearLevel::DEFAULT_ELIGIBLE);
        $this->assertSame(
            StudentYearLevel::DEFAULT_ELIGIBLE,
            SchoolCourseRequirement::query()->first()?->eligible_year_levels
        );
    }

    public function test_create_and_update_accept_custom_year_levels_including_second(): void
    {
        $school = $this->createSchool();
        Sanctum::actingAs($school['user']);

        $created = $this->postJson('/api/school-course-requirements', [
            'courseName' => 'BS Nursing',
            'requiredHours' => 600,
            'eligibleYearLevels' => ['2nd Year', '3rd Year', '5th Year'],
        ])->assertCreated();
        $created->assertJsonPath('data.eligibleYearLevels', ['2nd Year', '3rd Year', '5th Year']);

        $id = $created->json('data.id');
        $this->patchJson('/api/school-course-requirements/'.$id, [
            'courseName' => 'BS Nursing',
            'requiredHours' => 600,
            'eligibleYearLevels' => ['2nd Year'],
        ])
            ->assertOk()
            ->assertJsonPath('data.eligibleYearLevels', ['2nd Year']);
    }

    public function test_empty_and_invalid_year_levels_rejected(): void
    {
        $school = $this->createSchool();
        Sanctum::actingAs($school['user']);

        $this->postJson('/api/school-course-requirements', [
            'courseName' => 'BS Computer Science',
            'requiredHours' => 486,
            'eligibleYearLevels' => [],
        ])->assertStatus(422);

        $this->postJson('/api/school-course-requirements', [
            'courseName' => 'BS Computer Science',
            'requiredHours' => 486,
            'eligibleYearLevels' => ['Senior'],
        ])->assertStatus(422);
    }

    public function test_teacher_and_student_cannot_manage_course_config(): void
    {
        $school = $this->createSchool();
        $teacher = $this->attachStaff($school, ['manage_profile'], 'Teacher');
        Sanctum::actingAs($teacher);
        $this->postJson('/api/school-course-requirements', [
            'courseName' => 'BS IT',
            'requiredHours' => 400,
            'eligibleYearLevels' => ['3rd Year'],
        ])->assertForbidden();

        $apply = $this->createApplyContext($school, 'company', '4th Year');
        Sanctum::actingAs($apply['studentUser']);
        $this->getJson('/api/school-course-requirements')->assertForbidden();
    }

    public function test_cross_school_update_is_not_found(): void
    {
        $first = $this->createSchool();
        $second = $this->createSchool();
        Sanctum::actingAs($first['user']);
        $id = $this->postJson('/api/school-course-requirements', [
            'courseName' => 'BS IT',
            'requiredHours' => 400,
            'eligibleYearLevels' => ['3rd Year', '4th Year'],
        ])->json('data.id');

        Sanctum::actingAs($second['user']);
        $this->patchJson('/api/school-course-requirements/'.$id, [
            'courseName' => 'BS IT',
            'requiredHours' => 100,
            'eligibleYearLevels' => ['1st Year'],
        ])->assertNotFound();
    }

    public function test_student_create_rejects_ineligible_year_and_accepts_second_when_allowed(): void
    {
        $school = $this->createSchool(['org.manage_students', 'manage_users', 'org.manage_members']);
        Sanctum::actingAs($school['user']);
        $this->postJson('/api/school-course-requirements', [
            'courseName' => 'BS Information Technology',
            'requiredHours' => 486,
            'eligibleYearLevels' => ['2nd Year', '3rd Year', '4th Year'],
        ])->assertCreated();

        $this->postJson('/api/school-students', [
            'email' => 'first.'.uniqid('', true).'@cvsu.edu.ph',
            'studentName' => 'First Year Student',
            'course' => 'BS Information Technology',
            'yearLevel' => '1st Year',
        ])->assertStatus(422);

        $this->postJson('/api/school-students', [
            'email' => 'second.'.uniqid('', true).'@cvsu.edu.ph',
            'studentName' => 'Second Year Student',
            'course' => 'BS Information Technology',
            'yearLevel' => '2nd Year',
        ])->assertCreated();
    }

    /**
     * @dataProvider hostTypes
     */
    public function test_apply_blocked_when_course_incomplete_and_allowed_when_ready(string $hostType): void
    {
        $school = $this->createSchool();
        $ctx = $this->createApplyContext($school, $hostType, '4th Year');

        Sanctum::actingAs($ctx['studentUser']);
        // No course requirement → incomplete setup
        $this->postJson('/api/applications', [
            'internship_id' => $ctx['internship']->id,
            'endorsement_url' => $this->seedEndorsementDocument($ctx['studentUser'])->file_url,
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'course_setup_incomplete');

        SchoolCourseRequirement::query()->create([
            'school_id' => $school['school']->id,
            'course_name' => 'BS Information Technology',
            'course_name_key' => SchoolCourseRequirement::courseNameKey('BS Information Technology'),
            'required_hours' => 486,
            'eligible_year_levels' => ['3rd Year', '4th Year'],
        ]);

        $this->postApplication($ctx['internship']->id, [], $ctx['studentUser'])->assertCreated();
    }

    /**
     * @dataProvider hostTypes
     */
    public function test_apply_rejects_first_year_unless_course_allows_it(string $hostType): void
    {
        $school = $this->createSchool();
        SchoolCourseRequirement::query()->create([
            'school_id' => $school['school']->id,
            'course_name' => 'BS Information Technology',
            'course_name_key' => SchoolCourseRequirement::courseNameKey('BS Information Technology'),
            'required_hours' => 486,
            'eligible_year_levels' => ['3rd Year', '4th Year'],
        ]);

        $blocked = $this->createApplyContext($school, $hostType, '1st Year');
        Sanctum::actingAs($blocked['studentUser']);
        $this->postJson('/api/applications', [
            'internship_id' => $blocked['internship']->id,
            'endorsement_url' => $this->seedEndorsementDocument($blocked['studentUser'])->file_url,
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'year_level_not_eligible');

        SchoolCourseRequirement::query()
            ->where('school_id', $school['school']->id)
            ->update(['eligible_year_levels' => json_encode(['1st Year', '2nd Year', '3rd Year'])]);

        $allowed = $this->createApplyContext($school, $hostType, '2nd Year');
        Sanctum::actingAs($allowed['studentUser']);
        $this->postApplication($allowed['internship']->id, [], $allowed['studentUser'])->assertCreated();
    }

    public function test_existing_student_year_not_modified_when_flagged(): void
    {
        $school = $this->createSchool();
        SchoolCourseRequirement::query()->create([
            'school_id' => $school['school']->id,
            'course_name' => 'BS Information Technology',
            'course_name_key' => SchoolCourseRequirement::courseNameKey('BS Information Technology'),
            'required_hours' => 486,
            'eligible_year_levels' => ['3rd Year', '4th Year'],
        ]);

        $studentUser = User::factory()->create(['role' => 'student', 'email' => 'flag.'.uniqid('', true).'@example.com']);
        $student = Student::query()->create([
            'user_id' => $studentUser->id,
            'school_id' => $school['school']->id,
            'organization_id' => $school['organization']->id,
            'school_name' => $school['school']->institution_name,
            'course' => 'BS Information Technology',
            'year_level' => '1st Year',
        ]);

        Sanctum::actingAs($school['user']);
        $flagged = $this->getJson('/api/school-course-requirements/flagged-students')
            ->assertOk()
            ->json('data');

        $this->assertNotEmpty($flagged);
        $this->assertSame('1st Year', $student->fresh()->year_level);
        $this->assertTrue(collect($flagged)->contains(fn ($row) => (string) $row['id'] === (string) $student->id));
    }

    public function test_hours_change_does_not_rewrite_application_snapshot_used_by_accessor(): void
    {
        $school = $this->createSchool();
        $requirement = SchoolCourseRequirement::query()->create([
            'school_id' => $school['school']->id,
            'course_name' => 'BS Information Technology',
            'course_name_key' => SchoolCourseRequirement::courseNameKey('BS Information Technology'),
            'required_hours' => 486,
            'eligible_year_levels' => ['3rd Year', '4th Year'],
        ]);
        $ctx = $this->createApplyContext($school, 'company', '4th Year');
        Sanctum::actingAs($ctx['studentUser']);
        $appId = $this->postApplication($ctx['internship']->id, [], $ctx['studentUser'])->json('data.id');

        $application = Application::query()->findOrFail($appId);
        $this->assertSame(486, (int) $application->required_ojt_hours);
        $this->assertSame(486, app(RequiredOjtHoursAccessor::class)->forApplication($application));

        $requirement->forceFill(['required_hours' => 600])->save();
        $application = $application->fresh();
        $this->assertSame(486, (int) $application->required_ojt_hours);
        $this->assertSame(486, app(RequiredOjtHoursAccessor::class)->forApplication($application));
    }

    public function test_migration_default_eligible_years_for_existing_rows(): void
    {
        $school = $this->createSchool();
        // Simulate a pre-Phase-B row by inserting without going through model defaults carefully.
        $id = SchoolCourseRequirement::query()->insertGetId([
            'school_id' => $school['school']->id,
            'course_name' => 'Legacy Course',
            'course_name_key' => 'legacy course',
            'required_hours' => 300,
            'eligible_year_levels' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Re-run the Phase B backfill logic used by the migration.
        \Illuminate\Support\Facades\DB::table('school_course_requirements')
            ->whereNull('eligible_year_levels')
            ->orWhere('eligible_year_levels', '')
            ->update(['eligible_year_levels' => json_encode(StudentYearLevel::DEFAULT_ELIGIBLE)]);

        $row = SchoolCourseRequirement::query()->findOrFail($id);
        $this->assertSame(300, (int) $row->required_hours);
        $this->assertSame(StudentYearLevel::DEFAULT_ELIGIBLE, StudentYearLevel::normalizeList($row->eligible_year_levels));
    }

    public static function hostTypes(): array
    {
        return [
            'company' => ['company'],
            'municipal hall' => ['municipal_hall'],
        ];
    }
}
