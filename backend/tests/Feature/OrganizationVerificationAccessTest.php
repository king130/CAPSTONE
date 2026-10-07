<?php

namespace Tests\Feature;

use App\Models\Company;
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

class OrganizationVerificationAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{user: User, organization: Organization, school: School, role: Role}
     */
    private function createSchoolOrg(string $verificationStatus = 'pending', bool $orgActive = true): array
    {
        Permission::query()->updateOrCreate(
            ['key' => 'org.manage_internships'],
            ['scope' => 'organization', 'description' => 'Create and update internships']
        );
        Permission::query()->updateOrCreate(
            ['key' => 'org.manage_members'],
            ['scope' => 'organization', 'description' => 'Manage organization members']
        );
        Permission::query()->updateOrCreate(
            ['key' => 'manage_users'],
            ['scope' => 'organization', 'description' => 'Manage users']
        );

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
            'name' => 'Verification School Org',
            'type' => 'school',
            'subscription_id' => $subscription->id,
            'owner_user_id' => $user->id,
            'settings' => [],
            'is_active' => $orgActive,
        ]);

        $role = Role::query()->create([
            'tenant_id' => $organization->id,
            'name' => 'School Admin',
            'slug' => 'school_admin_'.uniqid(),
            'scope' => 'organization',
            'organization_type' => 'school',
            'is_system' => false,
        ]);

        $permissionIds = Permission::query()
            ->whereIn('key', ['org.manage_internships', 'org.manage_members', 'manage_users'])
            ->pluck('id')
            ->all();
        $role->permissions()->sync($permissionIds);

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
            'institution_name' => 'Verification School',
            'subscription_code' => 'SCH-'.strtoupper(substr(uniqid(), -4)),
            'verification_status' => $verificationStatus,
        ]);

        return compact('user', 'organization', 'school', 'role');
    }

    /**
     * @return array{user: User, organization: Organization, company: Company, role: Role}
     */
    private function createCompanyOrg(string $verificationStatus = 'pending', bool $orgActive = true): array
    {
        Permission::query()->updateOrCreate(
            ['key' => 'org.manage_internships'],
            ['scope' => 'organization', 'description' => 'Create and update internships']
        );

        $user = User::factory()->create([
            'role' => 'company',
            'email' => 'company.'.uniqid('', true).'@example.com',
            'is_active' => true,
        ]);

        $subscription = Subscription::query()->create([
            'plan' => 'standard',
            'status' => 'active',
            'billing_cycle' => 'monthly',
        ]);

        $organization = Organization::query()->create([
            'name' => 'Verification Company Org',
            'type' => 'company',
            'subscription_id' => $subscription->id,
            'owner_user_id' => $user->id,
            'settings' => [],
            'is_active' => $orgActive,
        ]);

        $role = Role::query()->create([
            'tenant_id' => $organization->id,
            'name' => 'Company Admin',
            'slug' => 'company_admin_'.uniqid(),
            'scope' => 'organization',
            'organization_type' => 'company',
            'is_system' => false,
        ]);

        $permission = Permission::query()->where('key', 'org.manage_internships')->firstOrFail();
        $role->permissions()->sync([$permission->id]);

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

        $company = Company::query()->create([
            'user_id' => $user->id,
            'organization_id' => $organization->id,
            'company_name' => 'Verification Company',
            'verification_status' => $verificationStatus,
        ]);

        return compact('user', 'organization', 'company', 'role');
    }

    public function test_pending_school_cannot_create_internship(): void
    {
        $context = $this->createSchoolOrg('pending');
        Sanctum::actingAs($context['user']);
        $before = Internship::query()->count();

        $response = $this->postJson('/api/internships', [
            'title' => 'Pending School Internship',
            'status' => 'active',
        ]);

        $response->assertForbidden();
        $response->assertJsonPath('message', 'Organization verification is pending.');
        $this->assertSame($before, Internship::query()->count());
    }

    public function test_pending_company_cannot_create_internship(): void
    {
        $context = $this->createCompanyOrg('pending');
        Sanctum::actingAs($context['user']);
        $before = Internship::query()->count();

        $response = $this->postJson('/api/internships', [
            'title' => 'Pending Company Internship',
            'status' => 'active',
        ]);

        $response->assertForbidden();
        $response->assertJsonPath('message', 'Organization verification is pending.');
        $this->assertSame($before, Internship::query()->count());
    }

    public function test_pending_school_cannot_invite_student(): void
    {
        $context = $this->createSchoolOrg('pending');
        Sanctum::actingAs($context['user']);
        $usersBefore = User::query()->count();
        $studentsBefore = Student::query()->count();

        $response = $this->postJson('/api/school-students', [
            'email' => 'invite.'.uniqid('', true).'@example.com',
            'studentName' => 'Pending Invite',
            'course' => 'BSIT',
        ]);

        $response->assertForbidden();
        $response->assertJsonPath('message', 'Organization verification is pending.');
        $this->assertSame($usersBefore, User::query()->count());
        $this->assertSame($studentsBefore, Student::query()->count());
    }

    public function test_pending_company_cannot_perform_representative_org_action(): void
    {
        $context = $this->createCompanyOrg('pending');
        Sanctum::actingAs($context['user']);

        $response = $this->postJson('/api/agreements', [
            'title' => 'Should Not Create',
        ]);

        $response->assertForbidden();
        $response->assertJsonPath('message', 'Organization verification is pending.');
    }

    public function test_approved_school_can_create_internship(): void
    {
        $context = $this->createSchoolOrg('approved');
        Sanctum::actingAs($context['user']);

        $response = $this->postJson('/api/internships', [
            'title' => 'Approved School Internship',
            'status' => 'active',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('internships', [
            'title' => 'Approved School Internship',
            'school_id' => $context['school']->id,
        ]);
    }

    public function test_approved_company_can_create_internship(): void
    {
        $context = $this->createCompanyOrg('approved');
        Sanctum::actingAs($context['user']);

        $response = $this->postJson('/api/internships', [
            'title' => 'Approved Company Internship',
            'status' => 'active',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('internships', [
            'title' => 'Approved Company Internship',
            'company_id' => $context['company']->id,
        ]);
    }

    public function test_inactive_organization_is_blocked(): void
    {
        $context = $this->createCompanyOrg('approved', false);
        Sanctum::actingAs($context['user']);
        $before = Internship::query()->count();

        $response = $this->postJson('/api/internships', [
            'title' => 'Inactive Org Internship',
            'status' => 'active',
        ]);

        $response->assertForbidden();
        $response->assertJsonPath('message', 'Organization is inactive.');
        $this->assertSame($before, Internship::query()->count());
    }

    public function test_student_is_not_blocked_by_school_verification_status(): void
    {
        $school = $this->createSchoolOrg('pending');

        $studentUser = User::factory()->create([
            'role' => 'student',
            'email' => 'student.'.uniqid('', true).'@example.com',
            'is_active' => true,
            'profile_setup_complete' => true,
        ]);

        Student::query()->create([
            'user_id' => $studentUser->id,
            'school_id' => $school['school']->id,
            'organization_id' => $school['organization']->id,
            'school_name' => $school['school']->institution_name,
            'course' => 'BSIT',
        ]);

        Sanctum::actingAs($studentUser);

        $response = $this->getJson('/api/internships/eligible');

        $response->assertOk();
        $response->assertJsonStructure(['data']);
    }

    public function test_platform_admin_is_not_blocked_by_organization_gate(): void
    {
        Permission::query()->updateOrCreate(
            ['key' => 'platform.manage_users'],
            ['scope' => 'platform', 'description' => 'Manage platform users']
        );

        $platformRole = Role::query()->create([
            'name' => 'Platform Admin',
            'slug' => 'platform_admin_'.uniqid(),
            'scope' => 'platform',
            'is_system' => true,
        ]);
        $platformRole->permissions()->sync([
            Permission::query()->where('key', 'platform.manage_users')->value('id'),
        ]);

        $admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin.'.uniqid('', true).'@example.com',
            'platform_role_id' => $platformRole->id,
            'is_active' => true,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/users');

        $response->assertOk();
        $response->assertJsonStructure(['data']);
    }

    public function test_pending_user_can_authenticate_and_access_profile(): void
    {
        $context = $this->createCompanyOrg('pending');

        $login = $this->postJson('/api/auth/login', [
            'email' => $context['user']->email,
            'password' => 'password',
        ]);

        $login->assertOk();
        $login->assertJsonPath('user.verificationStatus', 'pending');

        Sanctum::actingAs($context['user']);
        $me = $this->getJson('/api/auth/me');
        $me->assertOk();
        $me->assertJsonPath('verificationStatus', 'pending');
    }
}
