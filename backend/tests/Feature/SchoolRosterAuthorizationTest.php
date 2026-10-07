<?php

namespace Tests\Feature;

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

class SchoolRosterAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<int, string>  $permissionKeys
     * @return array{
     *   user: User,
     *   membership: OrganizationMembership,
     *   organization: Organization,
     *   school: School,
     *   role: Role,
     *   student: Student
     * }
     */
    private function createPrimarySchoolContext(array $permissionKeys = ['manage_users', 'org.manage_members']): array
    {
        foreach ([
            'manage_users' => 'Manage users',
            'org.manage_members' => 'Manage organization members',
            'org.manage_roles' => 'Manage organization roles',
            'view_reports' => 'View reports',
        ] as $key => $description) {
            Permission::query()->updateOrCreate(
                ['key' => $key],
                ['scope' => 'organization', 'description' => $description]
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
            'name' => 'Roster Auth School Org',
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

        if ($permissionKeys !== []) {
            $role->permissions()->sync(
                Permission::query()->whereIn('key', $permissionKeys)->pluck('id')->all()
            );
        }

        $membership = OrganizationMembership::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
            'status' => 'active',
            'title' => 'Primary Admin',
            'permissions_override' => ['grant' => [], 'deny' => []],
        ]);

        $user->forceFill([
            'tenant_id' => $organization->id,
            'role_id' => $role->id,
        ])->save();

        $school = School::query()->create([
            'user_id' => $user->id,
            'organization_id' => $organization->id,
            'institution_name' => 'Roster Auth School',
            'subscription_code' => 'RST-'.strtoupper(substr(uniqid(), -4)),
            'verification_status' => 'approved',
        ]);

        $studentUser = User::factory()->create([
            'role' => 'student',
            'email' => 'student.'.uniqid('', true).'@example.com',
            'is_active' => true,
            'is_temporary' => true,
            'must_change_password' => true,
            'profile_setup_complete' => false,
        ]);

        $student = Student::query()->create([
            'user_id' => $studentUser->id,
            'school_id' => $school->id,
            'school_name' => $school->institution_name,
            'school_subscription_code' => $school->subscription_code,
            'course' => 'BSIT',
            'year_level' => '4',
        ]);

        return compact('user', 'membership', 'organization', 'school', 'role', 'student');
    }

    private function stripRosterPermissions(Role $role): void
    {
        $role->permissions()->sync([]);
    }

    public function test_demoted_primary_school_user_cannot_list_roster(): void
    {
        $context = $this->createPrimarySchoolContext();
        Sanctum::actingAs($context['user']);

        $this->getJson('/api/school-students')->assertOk();

        $this->stripRosterPermissions($context['role']);
        $context['user']->unsetRelation('organizationMemberships');

        $response = $this->getJson('/api/school-students');
        $response->assertForbidden();
    }

    public function test_legacy_role_and_school_alone_do_not_authorize_roster(): void
    {
        $context = $this->createPrimarySchoolContext([]);
        Sanctum::actingAs($context['user']);

        $this->assertSame('school', $context['user']->role);
        $this->assertNotNull($context['user']->fresh()->school);

        $email = 'invite.'.uniqid('', true).'@example.com';
        $response = $this->postJson('/api/school-students', [
            'email' => $email,
            'studentName' => 'Should Fail',
            'course' => 'BSIT',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => $email]);
    }

    public function test_inactive_membership_cannot_manage_roster_with_legacy_school(): void
    {
        $context = $this->createPrimarySchoolContext();
        $context['membership']->forceFill(['status' => 'inactive'])->save();
        $context['user']->unsetRelation('organizationMemberships');

        Sanctum::actingAs($context['user']->fresh());

        $this->assertSame('school', $context['user']->role);
        $this->assertNotNull($context['user']->fresh()->school);

        $response = $this->getJson('/api/school-students');
        $response->assertForbidden();
    }

    public function test_authorized_school_manager_can_invite_student(): void
    {
        $context = $this->createPrimarySchoolContext(['manage_users', 'org.manage_members']);
        Sanctum::actingAs($context['user']);

        $email = 'invite.'.uniqid('', true).'@example.com';
        $response = $this->postJson('/api/school-students', [
            'email' => $email,
            'studentName' => 'Invited Student',
            'course' => 'BSIT',
            'yearLevel' => '3',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('users', ['email' => $email, 'role' => 'student']);
    }

    public function test_student_cannot_access_school_roster(): void
    {
        $context = $this->createPrimarySchoolContext();
        $studentUser = $context['student']->user;
        Sanctum::actingAs($studentUser);

        $response = $this->getJson('/api/school-students');
        $response->assertForbidden();

        $me = $this->getJson('/api/auth/me');
        $me->assertOk();
    }

    public function test_demoted_primary_school_user_cannot_export_roster(): void
    {
        $context = $this->createPrimarySchoolContext();
        $this->stripRosterPermissions($context['role']);
        $context['user']->unsetRelation('organizationMemberships');
        Sanctum::actingAs($context['user']);

        $response = $this->get('/api/school-students/export');
        $response->assertForbidden();
    }

    public function test_demoted_primary_school_user_cannot_resend_setup_link(): void
    {
        $context = $this->createPrimarySchoolContext();
        $this->stripRosterPermissions($context['role']);
        $context['user']->unsetRelation('organizationMemberships');
        Sanctum::actingAs($context['user']);

        $response = $this->postJson(
            '/api/school-students/'.$context['student']->id.'/resend-setup-link'
        );
        $response->assertForbidden();
    }

    public function test_demoted_primary_school_user_cannot_update_or_delete_student(): void
    {
        $context = $this->createPrimarySchoolContext();
        $this->stripRosterPermissions($context['role']);
        $context['user']->unsetRelation('organizationMemberships');
        Sanctum::actingAs($context['user']);

        $patch = $this->patchJson('/api/school-students/'.$context['student']->id, [
            'course' => 'Hacked Course',
        ]);
        $patch->assertForbidden();
        $context['student']->refresh();
        $this->assertSame('BSIT', $context['student']->course);

        $delete = $this->deleteJson('/api/school-students/'.$context['student']->id);
        $delete->assertForbidden();
        $this->assertDatabaseHas('students', ['id' => $context['student']->id]);
    }
}
