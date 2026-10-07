<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Certificate;
use App\Models\Company;
use App\Models\Contract;
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

class OjtLogIntegrityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{
     *   schoolUser: User,
     *   school: School,
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
            'org.approve_certificates',
            'org.approve_ojt_logs',
            'org.view_reports',
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
            'name' => 'OJT School Org',
            'type' => 'school',
            'subscription_id' => $schoolSub->id,
            'owner_user_id' => $schoolUser->id,
            'settings' => ['required_ojt_hours' => 10],
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
                'org.approve_certificates',
                'org.approve_ojt_logs',
                'org.view_reports',
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
            'institution_name' => 'OJT School',
            'subscription_code' => 'OJT-'.strtoupper(substr(uniqid(), -4)),
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
            'name' => 'OJT Company Org',
            'type' => 'company',
            'subscription_id' => $companySub->id,
            'owner_user_id' => $companyUser->id,
            'settings' => ['required_ojt_hours' => 10],
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
                'org.approve_certificates',
                'org.approve_ojt_logs',
                'org.view_reports',
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
            'company_name' => 'OJT Company',
            'verification_status' => 'approved',
        ]);
        $internship = Internship::query()->create([
            'company_id' => $company->id,
            'host_type' => 'company',
            'title' => 'OJT Internship',
            'status' => 'active',
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
            'subject' => 'OJT MOA',
        ]);

        $studentUser = User::factory()->create([
            'role' => 'student',
            'email' => 'student.'.uniqid('', true).'@example.com',
            'name' => 'OJT Student',
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
            'companyUser',
            'company',
            'companyOrg',
            'internship',
            'studentUser',
            'student'
        );
    }

    private function makeApplication(array $ctx, string $status, ?Student $student = null, ?User $studentUser = null): Application
    {
        $student ??= $ctx['student'];
        $studentUser ??= $ctx['studentUser'];

        return Application::query()->create([
            'internship_id' => $ctx['internship']->id,
            'student_id' => $student->id,
            'company_id' => $ctx['company']->id,
            'status' => $status,
            'internship_title' => 'OJT Internship',
            'student_name' => $studentUser->name,
            'student_email' => $studentUser->email,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function logPayload(?int $applicationId = null): array
    {
        $payload = [
            'date' => now()->toDateString(),
            'timeIn' => '09:00',
            'timeOut' => '17:00',
            'tasksDone' => 'Completed assigned OJT tasks',
        ];

        if ($applicationId !== null) {
            $payload['application_id'] = $applicationId;
        }

        return $payload;
    }

    public function test_student_cannot_create_placement_less_ojt_log(): void
    {
        $ctx = $this->createContext();
        Sanctum::actingAs($ctx['studentUser']);
        $before = OjtLog::query()->count();

        $response = $this->postJson('/api/ojt-logs', $this->logPayload());

        $response->assertStatus(422);
        $response->assertJsonPath('code', 'accepted_application_required');
        $this->assertSame($before, OjtLog::query()->count());
    }

    public function test_student_cannot_log_against_non_accepted_application(): void
    {
        $ctx = $this->createContext();
        $application = $this->makeApplication($ctx, 'endorsed');

        Sanctum::actingAs($ctx['studentUser']);
        $before = OjtLog::query()->count();

        $response = $this->postJson('/api/ojt-logs', $this->logPayload($application->id));

        $response->assertStatus(422);
        $response->assertJsonPath('code', 'application_not_accepted');
        $this->assertSame($before, OjtLog::query()->count());
    }

    public function test_student_can_log_against_accepted_application(): void
    {
        $ctx = $this->createContext();
        $application = $this->makeApplication($ctx, 'accepted');

        Sanctum::actingAs($ctx['studentUser']);
        $response = $this->postJson('/api/ojt-logs', $this->logPayload($application->id));

        $response->assertCreated();
        $response->assertJsonPath('data.applicationId', (string) $application->id);
        $response->assertJsonPath('data.status', 'pending');

        $log = OjtLog::query()->where('application_id', $application->id)->first();
        $this->assertNotNull($log);
        $this->assertSame((int) $ctx['student']->id, (int) $log->student_id);
        $this->assertSame((int) $application->internship_id, (int) $log->internship_id);
    }

    public function test_student_cannot_use_another_students_accepted_application(): void
    {
        $ctx = $this->createContext();
        $otherUser = User::factory()->create([
            'role' => 'student',
            'email' => 'other.'.uniqid('', true).'@example.com',
            'is_active' => true,
        ]);
        $otherStudent = Student::query()->create([
            'user_id' => $otherUser->id,
            'school_id' => $ctx['school']->id,
            'organization_id' => $ctx['school']->organization_id,
            'school_name' => $ctx['school']->institution_name,
            'school_subscription_code' => $ctx['school']->subscription_code,
            'course' => 'BSCS',
        ]);
        $otherApplication = $this->makeApplication($ctx, 'accepted', $otherStudent, $otherUser);

        Sanctum::actingAs($ctx['studentUser']);
        $before = OjtLog::query()->count();

        $response = $this->postJson('/api/ojt-logs', $this->logPayload($otherApplication->id));

        $response->assertStatus(422);
        $response->assertJsonPath('code', 'application_not_found');
        $this->assertSame($before, OjtLog::query()->count());
    }

    public function test_certificate_does_not_count_null_application_logs(): void
    {
        $ctx = $this->createContext();
        $application = $this->makeApplication($ctx, 'accepted');

        OjtLog::query()->create([
            'student_id' => $ctx['student']->id,
            'application_id' => $application->id,
            'internship_id' => $ctx['internship']->id,
            'organization_id' => $ctx['companyOrg']->id,
            'log_date' => now()->subDays(2)->toDateString(),
            'time_in' => '09:00',
            'time_out' => '13:00',
            'hours' => 4,
            'tasks' => 'Partial placement hours',
            'status' => 'approved',
        ]);

        OjtLog::query()->create([
            'student_id' => $ctx['student']->id,
            'application_id' => null,
            'internship_id' => $ctx['internship']->id,
            'organization_id' => $ctx['companyOrg']->id,
            'log_date' => now()->subDay()->toDateString(),
            'time_in' => '09:00',
            'time_out' => '17:00',
            'hours' => 8,
            'tasks' => 'Orphan hours',
            'status' => 'approved',
        ]);

        Sanctum::actingAs($ctx['schoolUser']);
        $before = Certificate::query()->count();

        $response = $this->postJson('/api/certificates/generate', [
            'application_id' => $application->id,
        ]);

        $response->assertStatus(422);
        $this->assertSame($before, Certificate::query()->count());
        $this->assertStringContainsString('10', (string) $response->json('message'));
        $this->assertStringContainsString('4', (string) $response->json('message'));
    }

    public function test_certificate_issues_with_sufficient_application_scoped_hours(): void
    {
        $ctx = $this->createContext();
        $application = $this->makeApplication($ctx, 'accepted');

        OjtLog::query()->create([
            'student_id' => $ctx['student']->id,
            'application_id' => $application->id,
            'internship_id' => $ctx['internship']->id,
            'organization_id' => $ctx['companyOrg']->id,
            'log_date' => now()->subDay()->toDateString(),
            'time_in' => '08:00',
            'time_out' => '18:00',
            'hours' => 10,
            'tasks' => 'Completed required hours',
            'status' => 'approved',
        ]);

        Sanctum::actingAs($ctx['schoolUser']);
        $response = $this->postJson('/api/certificates/generate', [
            'application_id' => $application->id,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.applicationId', (string) $application->id);

        $certificate = Certificate::query()->where('application_id', $application->id)->first();
        $this->assertNotNull($certificate);
        $this->assertSame((int) $ctx['student']->id, (int) $certificate->student_id);
        $this->assertSame(10.0, (float) ($certificate->metadata['approved_hours'] ?? 0));
    }

    public function test_missing_application_id_uses_latest_accepted_application(): void
    {
        $ctx = $this->createContext();
        $older = $this->makeApplication($ctx, 'accepted');
        $older->forceFill(['updated_at' => now()->subDay()])->save();

        $newer = $this->makeApplication($ctx, 'accepted');
        $newer->forceFill(['updated_at' => now()])->save();

        Sanctum::actingAs($ctx['studentUser']);
        $response = $this->postJson('/api/ojt-logs', $this->logPayload());

        $response->assertCreated();
        $response->assertJsonPath('data.applicationId', (string) $newer->id);
        $this->assertSame(0, OjtLog::query()->where('application_id', $older->id)->count());
        $this->assertSame(1, OjtLog::query()->where('application_id', $newer->id)->count());
    }

    public function test_school_and_company_can_manage_required_hours_but_admin_cannot(): void
    {
        $ctx = $this->createContext();

        Sanctum::actingAs($ctx['schoolUser']);
        $this->getJson('/api/ojt-logs/required-hours')
            ->assertOk()
            ->assertJsonPath('data.requiredHours', 10);
        $this->patchJson('/api/ojt-logs/required-hours', ['requiredHours' => 480])
            ->assertOk()
            ->assertJsonPath('data.requiredHours', 480);
        $this->assertSame(480, (int) ($ctx['schoolUser']->fresh()->activeOrganization()?->settings['required_ojt_hours'] ?? 0));

        Sanctum::actingAs($ctx['companyUser']);
        $this->patchJson('/api/ojt-logs/required-hours', ['requiredHours' => 320])
            ->assertOk()
            ->assertJsonPath('data.requiredHours', 320);

        $admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin.'.uniqid('', true).'@example.com',
            'is_active' => true,
        ]);
        Sanctum::actingAs($admin);
        $this->getJson('/api/ojt-logs/required-hours')->assertForbidden();
        $this->patchJson('/api/ojt-logs/required-hours', ['requiredHours' => 100])->assertForbidden();
    }
}
