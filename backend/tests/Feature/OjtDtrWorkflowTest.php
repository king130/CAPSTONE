<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Company;
use App\Models\Internship;
use App\Models\OjtDtrSubmission;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Permission;
use App\Models\Role;
use App\Models\School;
use App\Models\Student;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OjtDtrWorkflowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function createContext(): array
    {
        foreach ([
            'org.approve_ojt_logs',
            'org.view_ojt_progress',
            'org.manage_internships',
        ] as $key) {
            Permission::query()->updateOrCreate(
                ['key' => $key],
                ['scope' => 'organization', 'description' => $key]
            );
        }

        $schoolUser = User::factory()->create(['role' => 'school', 'is_active' => true]);
        $schoolOrg = $this->makeOrg('DTR School', 'school', $schoolUser);
        $schoolRole = $this->makeRole($schoolOrg, 'school', ['org.approve_ojt_logs', 'org.view_ojt_progress']);
        $this->join($schoolOrg, $schoolUser, $schoolRole);
        $school = School::query()->create([
            'user_id' => $schoolUser->id,
            'organization_id' => $schoolOrg->id,
            'institution_name' => 'DTR School',
            'subscription_code' => 'DTR-'.strtoupper(substr(uniqid(), -4)),
            'verification_status' => 'approved',
        ]);

        $companyUser = User::factory()->create(['role' => 'company', 'is_active' => true]);
        $companyOrg = $this->makeOrg('DTR Company', 'company', $companyUser);
        $companyRole = $this->makeRole($companyOrg, 'company', ['org.approve_ojt_logs', 'org.manage_internships']);
        $this->join($companyOrg, $companyUser, $companyRole);
        $company = Company::query()->create([
            'user_id' => $companyUser->id,
            'organization_id' => $companyOrg->id,
            'company_name' => 'DTR Company',
            'verification_status' => 'approved',
        ]);
        $internship = Internship::query()->create([
            'company_id' => $company->id,
            'host_type' => 'company',
            'title' => 'DTR Internship',
            'status' => 'active',
        ]);

        $studentUser = User::factory()->create(['role' => 'student', 'name' => 'DTR Intern', 'is_active' => true]);
        $student = Student::query()->create([
            'user_id' => $studentUser->id,
            'school_id' => $school->id,
            'organization_id' => $schoolOrg->id,
            'school_name' => $school->institution_name,
            'school_subscription_code' => $school->subscription_code,
            'course' => 'BSIT',
            'year_level' => '3rd Year',
        ]);

        $application = Application::query()->create([
            'internship_id' => $internship->id,
            'student_id' => $student->id,
            'company_id' => $company->id,
            'status' => 'accepted',
            'internship_title' => 'DTR Internship',
            'student_name' => $studentUser->name,
            'student_email' => $studentUser->email,
        ]);

        return compact('schoolUser', 'school', 'schoolOrg', 'companyUser', 'company', 'studentUser', 'student', 'application');
    }

    private function makeOrg(string $name, string $type, User $owner): Organization
    {
        $subscription = Subscription::query()->create([
            'plan' => 'standard',
            'status' => 'active',
            'billing_cycle' => 'monthly',
        ]);

        return Organization::query()->create([
            'name' => $name,
            'type' => $type,
            'subscription_id' => $subscription->id,
            'owner_user_id' => $owner->id,
            'settings' => ['required_ojt_hours' => 20],
            'is_active' => true,
        ]);
    }

    /**
     * @param array<int, string> $keys
     */
    private function makeRole(Organization $organization, string $type, array $keys): Role
    {
        $role = Role::query()->create([
            'tenant_id' => $organization->id,
            'name' => 'Role '.uniqid(),
            'slug' => 'role_'.uniqid(),
            'scope' => 'organization',
            'organization_type' => $type,
        ]);
        $role->permissions()->sync(Permission::query()->whereIn('key', $keys)->pluck('id'));

        return $role;
    }

    private function join(Organization $organization, User $user, Role $role): void
    {
        OrganizationMembership::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
            'status' => 'active',
            'title' => 'Member',
        ]);
        $user->forceFill([
            'tenant_id' => $organization->id,
            'role_id' => $role->id,
        ])->save();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(int $applicationId, UploadedFile|null $file = null): array
    {
        return [
            'application_id' => $applicationId,
            'period_start' => '2026-10-01',
            'period_end' => '2026-10-07',
            'submitted_hours' => 4,
            'remarks' => 'Week one',
            'file' => $file ?? UploadedFile::fake()->create('weekly-dtr.pdf', 120, 'application/pdf'),
        ];
    }

    public function test_student_upload_goes_to_pending_company_then_company_and_coordinator_approve(): void
    {
        $ctx = $this->createContext();
        Sanctum::actingAs($ctx['studentUser']);

        $created = $this->post('/api/ojt-dtrs', $this->payload($ctx['application']->id), ['Accept' => 'application/json']);
        $created->assertCreated();
        $created->assertJsonPath('data.status', 'submitted');
        $created->assertJsonPath('data.displayStatus', 'submitted');
        $created->assertJsonPath('data.reviewStage', 'host');
        $dtrId = $created->json('data.id');

        Sanctum::actingAs($ctx['schoolUser']);
        $this->postJson("/api/ojt-dtrs/{$dtrId}/approve")->assertStatus(422);

        Sanctum::actingAs($ctx['companyUser']);
        $companyApproved = $this->postJson("/api/ojt-dtrs/{$dtrId}/approve");
        $companyApproved->assertOk();
        $companyApproved->assertJsonPath('data.status', 'submitted');
        $companyApproved->assertJsonPath('data.displayStatus', 'submitted');
        $companyApproved->assertJsonPath('data.reviewStage', 'school');
        $this->assertNotNull(OjtDtrSubmission::query()->find($dtrId)?->company_reviewed_at);

        Sanctum::actingAs($ctx['companyUser']);
        $this->postJson("/api/ojt-dtrs/{$dtrId}/approve")->assertStatus(422);

        Sanctum::actingAs($ctx['schoolUser']);
        $final = $this->postJson("/api/ojt-dtrs/{$dtrId}/approve");
        $final->assertOk();
        $final->assertJsonPath('data.status', 'approved');
        $this->assertSame('approved', OjtDtrSubmission::query()->find($dtrId)?->status);
    }

    public function test_company_submit_skips_to_pending_coordinator(): void
    {
        $ctx = $this->createContext();
        Sanctum::actingAs($ctx['companyUser']);

        $response = $this->post('/api/ojt-dtrs', $this->payload($ctx['application']->id), ['Accept' => 'application/json']);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'submitted');
        $response->assertJsonPath('data.displayStatus', 'submitted');
        $response->assertJsonPath('data.reviewStage', 'school');
        $this->assertDatabaseHas('ojt_dtr_submissions', [
            'application_id' => $ctx['application']->id,
            'status' => 'submitted',
            'review_stage' => 'school',
            'submitted_by_user_id' => $ctx['companyUser']->id,
        ]);
    }

    public function test_company_cannot_submit_for_another_companys_intern(): void
    {
        $ctx = $this->createContext();
        $otherUser = User::factory()->create(['role' => 'company', 'is_active' => true]);
        $otherOrg = $this->makeOrg('Other Co', 'company', $otherUser);
        $otherRole = $this->makeRole($otherOrg, 'company', ['org.manage_internships']);
        $this->join($otherOrg, $otherUser, $otherRole);
        Company::query()->create([
            'user_id' => $otherUser->id,
            'organization_id' => $otherOrg->id,
            'company_name' => 'Other Co',
            'verification_status' => 'approved',
        ]);

        Sanctum::actingAs($otherUser);
        $response = $this->post('/api/ojt-dtrs', $this->payload($ctx['application']->id), ['Accept' => 'application/json']);

        $response->assertForbidden();
        $this->assertSame(0, OjtDtrSubmission::query()->count());
    }

    public function test_invalid_and_oversized_files_are_rejected(): void
    {
        $ctx = $this->createContext();
        Sanctum::actingAs($ctx['companyUser']);

        $missing = $this->post('/api/ojt-dtrs', [
            'application_id' => $ctx['application']->id,
            'period_start' => '2026-10-01',
            'period_end' => '2026-10-07',
            'submitted_hours' => 4,
        ], ['Accept' => 'application/json']);
        $missing->assertStatus(422);

        $invalid = $this->post('/api/ojt-dtrs', $this->payload(
            $ctx['application']->id,
            UploadedFile::fake()->create('notes.txt', 20, 'text/plain')
        ), ['Accept' => 'application/json']);
        $invalid->assertStatus(422);

        $oversized = $this->post('/api/ojt-dtrs', $this->payload(
            $ctx['application']->id,
            UploadedFile::fake()->create('big.pdf', 10241, 'application/pdf')
        ), ['Accept' => 'application/json']);
        $oversized->assertStatus(422);
        $this->assertSame(0, OjtDtrSubmission::query()->count());
    }

    public function test_school_can_approve_and_only_approved_hours_count(): void
    {
        $ctx = $this->createContext();
        Sanctum::actingAs($ctx['studentUser']);
        $this->postJson('/api/ojt-logs', [
            'date' => '2026-10-02',
            'timeIn' => '09:00',
            'timeOut' => '17:00',
            'tasksDone' => 'Daily log',
            'application_id' => $ctx['application']->id,
        ])->assertCreated();

        Sanctum::actingAs($ctx['companyUser']);
        $logId = \App\Models\OjtLog::query()->firstOrFail()->id;
        $this->patchJson("/api/ojt-logs/{$logId}/status", ['status' => 'approved'])->assertOk();

        $created = $this->post('/api/ojt-dtrs', $this->payload($ctx['application']->id), ['Accept' => 'application/json']);
        $created->assertCreated();
        $dtrId = $created->json('data.id');

        Sanctum::actingAs($ctx['studentUser']);
        $pendingProgress = $this->getJson('/api/ojt-logs/progress');
        $pendingProgress->assertOk();
        $pendingProgress->assertJsonPath('data.totalApproved', 8);
        $this->assertEquals(4, $pendingProgress->json('data.totalPending'));

        Sanctum::actingAs($ctx['companyUser']);
        $this->postJson("/api/ojt-dtrs/{$dtrId}/approve")->assertStatus(422);

        Sanctum::actingAs($ctx['schoolUser']);
        $this->postJson("/api/ojt-dtrs/{$dtrId}/approve")->assertOk();
        $this->postJson("/api/ojt-dtrs/{$dtrId}/approve")->assertStatus(422);

        Sanctum::actingAs($ctx['studentUser']);
        $approvedProgress = $this->getJson('/api/ojt-logs/progress');
        $approvedProgress->assertJsonPath('data.totalApproved', 12);
    }

    public function test_rejection_requires_a_reason_and_does_not_count_hours(): void
    {
        $ctx = $this->createContext();
        Sanctum::actingAs($ctx['companyUser']);
        $dtrId = $this->post('/api/ojt-dtrs', $this->payload($ctx['application']->id), ['Accept' => 'application/json'])->json('data.id');

        Sanctum::actingAs($ctx['schoolUser']);
        $this->postJson("/api/ojt-dtrs/{$dtrId}/reject", [])->assertStatus(422);
        $this->postJson("/api/ojt-dtrs/{$dtrId}/reject", [
            'reason' => 'DTR period does not match the submitted OJT dates.',
        ])->assertOk();

        Sanctum::actingAs($ctx['studentUser']);
        $this->getJson('/api/ojt-logs/progress')->assertJsonPath('data.totalApproved', 0);

        Sanctum::actingAs($ctx['companyUser']);
        $list = $this->getJson('/api/ojt-dtrs');
        $list->assertOk();
        $list->assertJsonPath('data.0.rejectionReason', 'DTR period does not match the submitted OJT dates.');

        $resubmitted = $this->post("/api/ojt-dtrs/{$dtrId}/resubmit", $this->payload($ctx['application']->id), ['Accept' => 'application/json']);
        $resubmitted->assertCreated();
        $resubmitted->assertJsonPath('data.displayStatus', 'submitted');
        $resubmitted->assertJsonPath('data.status', 'submitted');
        $resubmitted->assertJsonPath('data.previousSubmissionId', $dtrId);
        $this->assertSame('rejected', OjtDtrSubmission::query()->find($dtrId)?->status);
    }

    public function test_another_school_cannot_review_the_dtr(): void
    {
        $ctx = $this->createContext();
        Sanctum::actingAs($ctx['companyUser']);
        $dtrId = $this->post('/api/ojt-dtrs', $this->payload($ctx['application']->id), ['Accept' => 'application/json'])->json('data.id');

        $otherUser = User::factory()->create(['role' => 'school', 'is_active' => true]);
        $otherOrg = $this->makeOrg('Other School', 'school', $otherUser);
        $otherRole = $this->makeRole($otherOrg, 'school', ['org.approve_ojt_logs']);
        $this->join($otherOrg, $otherUser, $otherRole);
        School::query()->create([
            'user_id' => $otherUser->id,
            'organization_id' => $otherOrg->id,
            'institution_name' => 'Other School',
            'subscription_code' => 'OTH-'.strtoupper(substr(uniqid(), -4)),
            'verification_status' => 'approved',
        ]);

        Sanctum::actingAs($otherUser);
        $this->postJson("/api/ojt-dtrs/{$dtrId}/approve")->assertForbidden();
        $this->assertSame('submitted', OjtDtrSubmission::query()->find($dtrId)?->status);
    }
}
