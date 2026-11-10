<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Permission;
use App\Models\Role;
use App\Models\School;
use App\Models\Student;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StudentProfileSelfServiceRestrictionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{owner: User, organization: Organization, school: School}
     */
    private function createApprovedSchool(string $institutionName = 'Provision School'): array
    {
        foreach ([
            'manage_users' => 'Manage users',
            'org.manage_members' => 'Manage organization members',
        ] as $key => $description) {
            Permission::query()->updateOrCreate(
                ['key' => $key],
                ['scope' => 'organization', 'description' => $description]
            );
        }

        $owner = User::factory()->create([
            'role' => 'school',
            'email' => 'school.'.uniqid('', true).'@example.com',
            'is_active' => true,
            'name' => 'School Admin',
        ]);

        $subscription = Subscription::query()->create([
            'plan' => 'standard',
            'status' => 'active',
            'billing_cycle' => 'monthly',
        ]);

        $organization = Organization::query()->create([
            'name' => $institutionName,
            'type' => 'school',
            'subscription_id' => $subscription->id,
            'owner_user_id' => $owner->id,
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

        $role->permissions()->sync(
            Permission::query()->whereIn('key', ['manage_users', 'org.manage_members'])->pluck('id')->all()
        );

        OrganizationMembership::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $owner->id,
            'role_id' => $role->id,
            'status' => 'active',
            'title' => 'Primary Admin',
            'permissions_override' => ['grant' => [], 'deny' => []],
        ]);

        $owner->forceFill([
            'tenant_id' => $organization->id,
            'role_id' => $role->id,
        ])->save();

        $school = School::query()->create([
            'user_id' => $owner->id,
            'organization_id' => $organization->id,
            'institution_name' => $institutionName,
            'subscription_code' => 'PRF-'.strtoupper(substr(uniqid(), -4)),
            'verification_status' => 'approved',
        ]);

        return compact('owner', 'organization', 'school');
    }

    /**
     * @return array{user: User, student: Student, school: School}
     */
    private function createProvisionedStudent(): array
    {
        $schoolContext = $this->createApprovedSchool();

        $user = User::factory()->create([
            'role' => 'student',
            'name' => 'Original Student Name',
            'email' => 'student.'.uniqid('', true).'@example.com',
            'password' => Hash::make('Password1'),
            'is_active' => true,
            'profile_setup_complete' => true,
        ]);

        $student = Student::query()->create([
            'user_id' => $user->id,
            'school_id' => $schoolContext['school']->id,
            'organization_id' => $schoolContext['organization']->id,
            'student_id_number' => 'STU-1001',
            'school_name' => $schoolContext['school']->institution_name,
            'school_subscription_code' => $schoolContext['school']->subscription_code,
            'course' => 'BSIT',
            'year_level' => '3rd Year',
            'preferred_field' => 'Software Development',
            'contact_number' => '09171234567',
        ]);

        return [
            'user' => $user,
            'student' => $student,
            'school' => $schoolContext['school'],
            'schoolOwner' => $schoolContext['owner'],
            'organization' => $schoolContext['organization'],
        ];
    }

    public function test_student_cannot_modify_name(): void
    {
        $context = $this->createProvisionedStudent();
        Sanctum::actingAs($context['user']);

        $response = $this->patchJson('/api/profile', [
            'displayName' => 'Hacked Name',
            'name' => 'Hacked Name',
        ]);

        $response->assertStatus(422);
        $context['user']->refresh();
        $this->assertSame('Original Student Name', $context['user']->name);
    }

    public function test_student_cannot_modify_course(): void
    {
        $context = $this->createProvisionedStudent();
        Sanctum::actingAs($context['user']);

        $response = $this->patchJson('/api/profile', [
            'profile' => ['course' => 'BSCS'],
        ]);

        $response->assertStatus(422);
        $context['student']->refresh();
        $this->assertSame('BSIT', $context['student']->course);
    }

    public function test_student_cannot_modify_year_level(): void
    {
        $context = $this->createProvisionedStudent();
        Sanctum::actingAs($context['user']);

        $response = $this->patchJson('/api/profile', [
            'profile' => ['yearLevel' => '4th Year'],
        ]);

        $response->assertStatus(422);
        $context['student']->refresh();
        $this->assertSame('3rd Year', $context['student']->year_level);
    }

    public function test_student_cannot_modify_student_number(): void
    {
        $context = $this->createProvisionedStudent();
        Sanctum::actingAs($context['user']);

        $response = $this->patchJson('/api/profile', [
            'profile' => ['studentNumber' => 'HACK-999'],
        ]);

        $response->assertStatus(422);
        $context['student']->refresh();
        $this->assertSame('STU-1001', $context['student']->student_id_number);
    }

    public function test_student_can_fill_missing_student_number_once(): void
    {
        $context = $this->createProvisionedStudent();
        $context['student']->student_id_number = null;
        $context['student']->save();
        $user = $context['user'];
        $profile = $user->profile ?? [];
        unset($profile['studentNumber'], $profile['studentId']);
        $user->profile = $profile;
        $user->save();

        Sanctum::actingAs($user);

        $response = $this->patchJson('/api/profile', [
            'profile' => ['studentNumber' => '  FILL-42  '],
        ]);

        $response->assertOk();
        $context['student']->refresh();
        $this->assertSame('FILL-42', $context['student']->student_id_number);
    }

    public function test_student_cannot_modify_school_name_or_school_id(): void
    {
        $other = $this->createApprovedSchool('Other University');
        $context = $this->createProvisionedStudent();
        Sanctum::actingAs($context['user']);

        $byName = $this->patchJson('/api/profile', [
            'profile' => ['schoolName' => 'Other University'],
        ]);
        $byName->assertStatus(422);

        $byId = $this->patchJson('/api/profile', [
            'profile' => ['schoolId' => (string) $other['school']->id],
        ]);
        $byId->assertStatus(422);

        $context['student']->refresh();
        $this->assertSame($context['school']->id, $context['student']->school_id);
        $this->assertSame($context['school']->institution_name, $context['student']->school_name);
    }

    public function test_student_cannot_modify_preferred_field(): void
    {
        $context = $this->createProvisionedStudent();
        Sanctum::actingAs($context['user']);

        $response = $this->patchJson('/api/profile', [
            'profile' => ['preferredField' => 'Cybersecurity'],
        ]);

        $response->assertStatus(422);
        $context['student']->refresh();
        $this->assertSame('Software Development', $context['student']->preferred_field);
    }

    public function test_student_can_update_contact_number(): void
    {
        $context = $this->createProvisionedStudent();
        Sanctum::actingAs($context['user']);

        $response = $this->patchJson('/api/profile', [
            'profile' => ['contactNumber' => '09998887766'],
        ]);

        $response->assertOk();
        $context['student']->refresh();
        $this->assertSame('09998887766', $context['student']->contact_number);
        $this->assertSame('BSIT', $context['student']->course);
        $this->assertSame('Original Student Name', $context['user']->fresh()->name);
    }

    public function test_student_can_still_change_password(): void
    {
        $context = $this->createProvisionedStudent();
        Sanctum::actingAs($context['user']);

        $response = $this->postJson('/api/auth/password', [
            'password' => 'NewPassword1',
            'password_confirmation' => 'NewPassword1',
        ]);

        $response->assertOk();
        $context['user']->refresh();
        $this->assertTrue(Hash::check('NewPassword1', $context['user']->password));
    }

    public function test_school_can_still_update_provisioned_student_details(): void
    {
        $context = $this->createProvisionedStudent();
        Sanctum::actingAs($context['schoolOwner']);

        $response = $this->patchJson('/api/school-students/'.$context['student']->id, [
            'studentName' => 'Updated By School',
            'studentNumber' => 'STU-2002',
            'course' => 'BSCS',
            'yearLevel' => '4th Year',
        ]);

        $response->assertOk();
        $context['student']->refresh();
        $context['user']->refresh();
        $this->assertSame('Updated By School', $context['user']->name);
        $this->assertSame('STU-2002', $context['student']->student_id_number);
        $this->assertSame('BSCS', $context['student']->course);
        $this->assertSame('4th Year', $context['student']->year_level);
    }

    public function test_school_and_company_profile_updates_remain_available(): void
    {
        $schoolContext = $this->createApprovedSchool('Editable School');
        Sanctum::actingAs($schoolContext['owner']);

        $schoolResponse = $this->patchJson('/api/profile', [
            'displayName' => 'School Contact Updated',
            'profile' => [
                'institutionName' => 'Editable School Renamed',
                'contactNumber' => '09170001111',
            ],
        ]);
        $schoolResponse->assertOk();
        $schoolContext['owner']->refresh();
        $schoolContext['school']->refresh();
        $this->assertSame('School Contact Updated', $schoolContext['owner']->name);
        $this->assertSame('Editable School Renamed', $schoolContext['school']->institution_name);

        $companyUser = User::factory()->create([
            'role' => 'company',
            'email' => 'company.'.uniqid('', true).'@example.com',
            'is_active' => true,
            'name' => 'Company Owner',
        ]);
        $subscription = Subscription::query()->create([
            'plan' => 'standard',
            'status' => 'active',
            'billing_cycle' => 'monthly',
        ]);
        $organization = Organization::query()->create([
            'name' => 'Editable Company',
            'type' => 'company',
            'subscription_id' => $subscription->id,
            'owner_user_id' => $companyUser->id,
            'settings' => [],
            'is_active' => true,
        ]);
        $companyUser->forceFill(['tenant_id' => $organization->id])->save();
        Company::query()->create([
            'user_id' => $companyUser->id,
            'organization_id' => $organization->id,
            'subscription_id' => $subscription->id,
            'company_name' => 'Editable Company',
            'verification_status' => 'approved',
        ]);

        Sanctum::actingAs($companyUser);
        $companyResponse = $this->patchJson('/api/profile', [
            'displayName' => 'Company Contact Updated',
            'profile' => [
                'companyName' => 'Editable Company Renamed',
                'contactNumber' => '09172223333',
            ],
        ]);
        $companyResponse->assertOk();
        $companyUser->refresh();
        $this->assertSame('Company Contact Updated', $companyUser->name);
        $this->assertSame(
            'Editable Company Renamed',
            Company::query()->where('user_id', $companyUser->id)->value('company_name')
        );
    }
}
