<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Company;
use App\Models\Internship;
use App\Models\OjtLog;
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

class PermissionScopeBleedTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param array<int, string> $permissionKeys
     * @return array{user: User, organization: Organization, school: School, role: Role}
     */
    private function createSchoolMember(array $permissionKeys, string $roleName): array
    {
        foreach (array_unique(array_merge($permissionKeys, [
            'org.view_reports',
            'org.reports_view',
            'view_reports',
            'org.reports_export',
            'org.approve_certificates',
            'org.approve_ojt_logs',
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
            'email' => strtolower(str_replace(' ', '.', $roleName)).'.'.uniqid('', true).'@example.com',
            'is_active' => true,
        ]);

        $subscription = Subscription::query()->create([
            'plan' => 'standard',
            'status' => 'active',
            'billing_cycle' => 'monthly',
        ]);

        $organization = Organization::query()->create([
            'name' => 'Scope School',
            'type' => 'school',
            'subscription_id' => $subscription->id,
            'owner_user_id' => $user->id,
            'settings' => ['required_ojt_hours' => 10],
            'is_active' => true,
        ]);

        $role = Role::query()->create([
            'tenant_id' => $organization->id,
            'name' => $roleName,
            'slug' => strtolower(str_replace(' ', '_', $roleName)).'_'.uniqid(),
            'scope' => 'organization',
            'organization_type' => 'school',
            'is_system' => false,
        ]);
        $role->permissions()->sync(
            Permission::query()->whereIn('key', $permissionKeys)->pluck('id')->all()
        );

        OrganizationMembership::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
            'status' => 'active',
            'title' => $roleName,
        ]);
        $user->forceFill([
            'tenant_id' => $organization->id,
            'role_id' => $role->id,
        ])->save();

        $school = School::query()->create([
            'user_id' => $user->id,
            'organization_id' => $organization->id,
            'institution_name' => 'Scope School',
            'subscription_code' => 'SCP-'.strtoupper(substr(uniqid(), -4)),
            'verification_status' => 'approved',
        ]);

        return compact('user', 'organization', 'school', 'role');
    }

    /**
     * @param array{organization: Organization, school: School} $schoolContext
     */
    private function attachMember(array $schoolContext, array $permissionKeys, string $roleName): User
    {
        $role = Role::query()->create([
            'tenant_id' => $schoolContext['organization']->id,
            'name' => $roleName,
            'slug' => strtolower(str_replace(' ', '_', $roleName)).'_'.uniqid(),
            'scope' => 'organization',
            'organization_type' => 'school',
            'is_system' => false,
        ]);
        $role->permissions()->sync(
            Permission::query()->whereIn('key', $permissionKeys)->pluck('id')->all()
        );

        $user = User::factory()->create([
            'role' => 'school',
            'email' => strtolower(str_replace(' ', '.', $roleName)).'.'.uniqid('', true).'@example.com',
            'is_active' => true,
            'tenant_id' => $schoolContext['organization']->id,
            'role_id' => $role->id,
        ]);

        OrganizationMembership::query()->create([
            'organization_id' => $schoolContext['organization']->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
            'status' => 'active',
            'title' => $roleName,
        ]);

        return $user;
    }

    /**
     * @param array{school: School, organization: Organization} $schoolContext
     */
    private function acceptedPlacement(array $schoolContext): Application
    {
        $companyUser = User::factory()->create([
            'role' => 'company',
            'email' => 'company.'.uniqid('', true).'@example.com',
            'is_active' => true,
        ]);
        $company = Company::query()->create([
            'user_id' => $companyUser->id,
            'company_name' => 'Scope Company',
            'verification_status' => 'approved',
        ]);
        $internship = Internship::query()->create([
            'company_id' => $company->id,
            'host_type' => 'company',
            'title' => 'Scope Internship',
            'status' => 'active',
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
            'school_subscription_code' => $schoolContext['school']->subscription_code,
            'course' => 'BSIT',
        ]);
        $application = Application::query()->create([
            'internship_id' => $internship->id,
            'student_id' => $student->id,
            'company_id' => $company->id,
            'status' => 'accepted',
            'internship_title' => 'Scope Internship',
            'student_name' => $studentUser->name,
            'student_email' => $studentUser->email,
        ]);
        OjtLog::query()->create([
            'student_id' => $student->id,
            'application_id' => $application->id,
            'internship_id' => $internship->id,
            'organization_id' => $schoolContext['organization']->id,
            'log_date' => now()->subDay()->toDateString(),
            'time_in' => '08:00',
            'time_out' => '18:00',
            'hours' => 10,
            'tasks' => 'Completed hours',
            'status' => 'approved',
        ]);

        return $application;
    }

    public function test_view_reports_cannot_export_but_export_permission_can(): void
    {
        $admin = $this->createSchoolMember([
            'org.view_reports',
            'org.reports_view',
            'view_reports',
            'org.reports_export',
        ], 'School Admin');
        $teacher = $this->attachMember($admin, [
            'org.view_reports',
            'org.reports_view',
            'view_reports',
        ], 'Teacher');

        Sanctum::actingAs($teacher);
        $this->getJson('/api/reports/kpi')->assertOk();
        $this->getJson('/api/reports/export?type=placements')->assertForbidden();

        Sanctum::actingAs($admin['user']);
        $this->get('/api/reports/export?type=placements')->assertOk();
    }

    public function test_view_reports_cannot_issue_certificates_but_approve_permission_can(): void
    {
        $admin = $this->createSchoolMember([
            'org.view_reports',
            'org.reports_view',
            'view_reports',
            'org.approve_certificates',
        ], 'School Admin');
        $teacher = $this->attachMember($admin, [
            'org.view_reports',
            'org.reports_view',
            'view_reports',
        ], 'Teacher');
        $application = $this->acceptedPlacement($admin);

        Sanctum::actingAs($teacher);
        $this->postJson('/api/certificates/generate', [
            'application_id' => $application->id,
        ])->assertForbidden();

        Sanctum::actingAs($admin['user']);
        $this->postJson('/api/certificates/generate', [
            'application_id' => $application->id,
        ])->assertCreated();
    }

    public function test_manage_profile_cannot_change_required_ojt_hours_but_ojt_admin_can(): void
    {
        $admin = $this->createSchoolMember([
            'org.manage_profile',
            'manage_profile',
            'org.approve_ojt_logs',
        ], 'School Admin');
        $teacher = $this->attachMember($admin, [
            'manage_profile',
            'org.manage_profile',
        ], 'Teacher');

        Sanctum::actingAs($teacher);
        $this->patchJson('/api/ojt-logs/required-hours', ['requiredHours' => 200])->assertForbidden();
        $this->assertSame(10, (int) ($admin['organization']->fresh()->settings['required_ojt_hours'] ?? 0));

        Sanctum::actingAs($admin['user']);
        $this->patchJson('/api/ojt-logs/required-hours', ['requiredHours' => 480])
            ->assertOk()
            ->assertJsonPath('data.requiredHours', 480);
    }
}
