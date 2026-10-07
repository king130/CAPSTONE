<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\ApplicationInterview;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Internship;
use App\Models\Notification;
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

class InterviewWorkflowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{
     *   schoolUser: User,
     *   school: School,
     *   companyUser: User,
     *   company: Company,
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
            'name' => 'Interview School Org',
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
            'institution_name' => 'Interview School',
            'subscription_code' => 'INT-'.strtoupper(substr(uniqid(), -4)),
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
            'name' => 'Interview Company Org',
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
            'company_name' => 'Interview Company',
            'verification_status' => 'approved',
        ]);
        $internship = Internship::query()->create([
            'company_id' => $company->id,
            'host_type' => 'company',
            'title' => 'Interview Internship',
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
            'subject' => 'Interview MOA',
        ]);

        $studentUser = User::factory()->create([
            'role' => 'student',
            'email' => 'student.'.uniqid('', true).'@example.com',
            'name' => 'Interview Student',
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
            'internship',
            'studentUser',
            'student'
        );
    }

    private function makeApplication(array $ctx, string $status): Application
    {
        return Application::query()->create([
            'internship_id' => $ctx['internship']->id,
            'student_id' => $ctx['student']->id,
            'company_id' => $ctx['company']->id,
            'status' => $status,
            'internship_title' => 'Interview Internship',
            'student_name' => $ctx['studentUser']->name,
            'student_email' => $ctx['studentUser']->email,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function proposePayload(Application $application): array
    {
        return [
            'application_id' => $application->id,
            'scheduled_at' => now()->addDays(3)->toIso8601String(),
            'duration_minutes' => 60,
            'mode' => 'online',
            'location_or_link' => 'https://meet.example.com/interview',
            'notes' => 'Bring portfolio',
        ];
    }

    public function test_unendorsed_application_cannot_create_interview(): void
    {
        $ctx = $this->createContext();
        $application = $this->makeApplication($ctx, 'submitted');

        Sanctum::actingAs($ctx['companyUser']);
        $beforeInterviews = ApplicationInterview::query()->count();
        $beforeNotifications = Notification::query()->count();

        $response = $this->postJson('/api/interviews', $this->proposePayload($application));

        $response->assertStatus(422);
        $response->assertJsonPath('code', 'application_not_endorsed');
        $this->assertSame($beforeInterviews, ApplicationInterview::query()->count());
        $this->assertSame($beforeNotifications, Notification::query()->count());
        $this->assertSame(0, Notification::query()->where('metadata->type', 'interview')->count());
    }

    public function test_endorsed_application_can_create_interview_and_notifies_student(): void
    {
        $ctx = $this->createContext();
        $application = $this->makeApplication($ctx, 'endorsed');

        Sanctum::actingAs($ctx['companyUser']);
        $response = $this->postJson('/api/interviews', $this->proposePayload($application));

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'proposed');

        $interview = ApplicationInterview::query()->where('application_id', $application->id)->first();
        $this->assertNotNull($interview);
        $this->assertSame('proposed', $interview->status);

        $studentNotification = Notification::query()
            ->where('user_id', $ctx['studentUser']->id)
            ->where('metadata->type', 'interview')
            ->first();
        $this->assertNotNull($studentNotification);
        $this->assertSame('Interview proposed', $studentNotification->title);
        $this->assertSame((string) $interview->id, $studentNotification->metadata['interviewId'] ?? null);
        $this->assertSame('/intern/applications', $studentNotification->metadata['redirectTo'] ?? null);
    }

    public function test_confirm_notifies_counterparties(): void
    {
        $ctx = $this->createContext();
        $application = $this->makeApplication($ctx, 'endorsed');
        $interview = ApplicationInterview::query()->create([
            'application_id' => $application->id,
            'scheduled_at' => now()->addDays(2),
            'duration_minutes' => 60,
            'mode' => 'online',
            'status' => 'proposed',
            'created_by_user_id' => $ctx['companyUser']->id,
        ]);

        Sanctum::actingAs($ctx['studentUser']);
        $before = Notification::query()->where('metadata->type', 'interview')->count();

        $response = $this->patchJson('/api/interviews/'.$interview->id.'/confirm');

        $response->assertOk();
        $response->assertJsonPath('data.status', 'confirmed');
        $this->assertSame('confirmed', $interview->fresh()->status);

        $this->assertGreaterThan($before, Notification::query()->where('metadata->type', 'interview')->count());
        $this->assertSame(
            0,
            Notification::query()
                ->where('user_id', $ctx['studentUser']->id)
                ->where('title', 'Interview confirmed')
                ->count()
        );
        $this->assertSame(
            1,
            Notification::query()
                ->where('user_id', $ctx['companyUser']->id)
                ->where('title', 'Interview confirmed')
                ->where('metadata->type', 'interview')
                ->count()
        );
        $this->assertSame(
            1,
            Notification::query()
                ->where('user_id', $ctx['schoolUser']->id)
                ->where('title', 'Interview confirmed')
                ->where('metadata->type', 'interview')
                ->count()
        );
    }

    public function test_cancel_notifies_counterparties(): void
    {
        $ctx = $this->createContext();
        $application = $this->makeApplication($ctx, 'endorsed');
        $interview = ApplicationInterview::query()->create([
            'application_id' => $application->id,
            'scheduled_at' => now()->addDays(2),
            'duration_minutes' => 60,
            'mode' => 'online',
            'status' => 'proposed',
            'created_by_user_id' => $ctx['companyUser']->id,
        ]);

        Sanctum::actingAs($ctx['companyUser']);
        $response = $this->patchJson('/api/interviews/'.$interview->id.'/cancel', [
            'notes' => 'Conflict',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'cancelled');
        $this->assertSame('cancelled', $interview->fresh()->status);

        $this->assertSame(
            0,
            Notification::query()
                ->where('user_id', $ctx['companyUser']->id)
                ->where('title', 'Interview cancelled')
                ->count()
        );
        $this->assertSame(
            1,
            Notification::query()
                ->where('user_id', $ctx['studentUser']->id)
                ->where('title', 'Interview cancelled')
                ->where('metadata->type', 'interview')
                ->count()
        );
        $this->assertSame(
            1,
            Notification::query()
                ->where('user_id', $ctx['schoolUser']->id)
                ->where('title', 'Interview cancelled')
                ->where('metadata->type', 'interview')
                ->count()
        );
    }

    public function test_failed_confirm_creates_no_notification(): void
    {
        $ctx = $this->createContext();
        $application = $this->makeApplication($ctx, 'endorsed');
        $interview = ApplicationInterview::query()->create([
            'application_id' => $application->id,
            'scheduled_at' => now()->addDays(2),
            'duration_minutes' => 60,
            'mode' => 'online',
            'status' => 'cancelled',
            'created_by_user_id' => $ctx['companyUser']->id,
        ]);

        Sanctum::actingAs($ctx['studentUser']);
        $before = Notification::query()->count();

        $response = $this->patchJson('/api/interviews/'.$interview->id.'/confirm');

        $response->assertStatus(422);
        $this->assertSame('cancelled', $interview->fresh()->status);
        $this->assertSame($before, Notification::query()->count());
    }
}
