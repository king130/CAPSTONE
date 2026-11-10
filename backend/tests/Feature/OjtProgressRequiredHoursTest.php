<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\ApplicationHoursAgreement;
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
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OjtProgressRequiredHoursTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_progress_uses_agreed_hours_agreement_value(): void
    {
        $ctx = $this->seedPlacement(agreedHours: 486);

        Sanctum::actingAs($ctx['studentUser']);

        $response = $this->getJson('/api/ojt-logs/progress');
        $response->assertOk();
        $response->assertJsonPath('data.requiredHours', 486);
    }

    public function test_school_progress_list_keeps_company_after_placement_ended(): void
    {
        $ctx = $this->seedPlacement(agreedHours: 400);
        $ctx['application']->forceFill([
            'status' => 'placement_ended',
            'tracker_stage' => 'placement_ended',
        ])->save();

        Sanctum::actingAs($ctx['schoolUser']);

        $response = $this->getJson('/api/ojt-logs/progress');
        $response->assertOk();

        $row = collect($response->json('data'))->firstWhere('internId', $ctx['student']->id);
        $this->assertNotNull($row);
        $this->assertSame(400, $row['requiredHours']);
        $this->assertSame($ctx['company']->company_name, $row['company']);
        $this->assertSame('placement_ended', $row['placementStatus']);
    }

    /**
     * @return array{
     *   studentUser: User,
     *   schoolUser: User,
     *   student: Student,
     *   company: Company,
     *   application: Application
     * }
     */
    private function seedPlacement(int $agreedHours): array
    {
        foreach ([
            'org.view_ojt_progress' => 'View OJT progress',
            'org.manage_students' => 'Manage students',
        ] as $key => $description) {
            Permission::query()->updateOrCreate(
                ['key' => $key],
                ['scope' => 'organization', 'description' => $description]
            );
        }

        $schoolUser = User::factory()->create([
            'role' => 'school',
            'email' => 'progress.school.'.uniqid().'@example.com',
            'password' => Hash::make('Password1'),
            'is_active' => true,
        ]);
        $schoolSub = Subscription::query()->create([
            'plan' => 'standard',
            'status' => 'active',
            'billing_cycle' => 'monthly',
        ]);
        $schoolOrg = Organization::query()->create([
            'name' => 'Progress School Org',
            'type' => 'school',
            'subscription_id' => $schoolSub->id,
            'owner_user_id' => $schoolUser->id,
            'settings' => ['required_ojt_hours' => 500],
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
            Permission::query()->whereIn('key', ['org.view_ojt_progress', 'org.manage_students'])->pluck('id')
        );
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
            'institution_name' => 'Progress School',
            'verification_status' => 'approved',
        ]);

        $companyUser = User::factory()->create([
            'role' => 'company',
            'email' => 'progress.company.'.uniqid().'@example.com',
            'is_active' => true,
        ]);
        $companySub = Subscription::query()->create([
            'plan' => 'standard',
            'status' => 'active',
            'billing_cycle' => 'monthly',
        ]);
        $companyOrg = Organization::query()->create([
            'name' => 'Progress Company Org',
            'type' => 'company',
            'subscription_id' => $companySub->id,
            'owner_user_id' => $companyUser->id,
            'settings' => [],
            'is_active' => true,
        ]);
        $companyUser->forceFill(['tenant_id' => $companyOrg->id])->save();
        $company = Company::query()->create([
            'user_id' => $companyUser->id,
            'organization_id' => $companyOrg->id,
            'company_name' => 'Progress Host Co',
            'verification_status' => 'approved',
        ]);

        $studentUser = User::factory()->create([
            'role' => 'student',
            'email' => 'progress.student.'.uniqid().'@example.com',
            'password' => Hash::make('Password1'),
            'is_active' => true,
            'profile_setup_complete' => true,
        ]);
        $student = Student::query()->create([
            'user_id' => $studentUser->id,
            'school_id' => $school->id,
            'organization_id' => $schoolOrg->id,
            'course' => 'BSIT',
            'year_level' => '4th Year',
            'student_id_number' => 'PROG-1',
        ]);

        $internship = Internship::query()->create([
            'company_id' => $company->id,
            'title' => 'Progress Internship',
            'status' => 'active',
            'host_required_hours' => 300,
        ]);

        $application = Application::query()->create([
            'internship_id' => $internship->id,
            'student_id' => $student->id,
            'company_id' => $company->id,
            'status' => 'accepted',
            'tracker_stage' => 'accepted',
            'internship_title' => $internship->title,
            'student_name' => $studentUser->name,
            'student_email' => $studentUser->email,
            'student_course' => 'BSIT',
            'required_ojt_hours' => $agreedHours,
        ]);

        ApplicationHoursAgreement::query()->create([
            'application_id' => $application->id,
            'status' => ApplicationHoursAgreement::STATUS_AGREED,
            'school_required_hours' => 500,
            'host_offered_hours' => 300,
            'proposed_hours' => $agreedHours,
            'agreed_hours' => $agreedHours,
            'school_confirmed_at' => now(),
            'host_confirmed_at' => now(),
        ]);

        return compact('studentUser', 'schoolUser', 'student', 'company', 'application');
    }
}
