<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\ApplicationHoursAgreement;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Internship;
use App\Models\OjtDtrSubmission;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Permission;
use App\Models\Role;
use App\Models\School;
use App\Models\SchoolCourseRequirement;
use App\Models\Student;
use App\Models\Subscription;
use App\Models\User;
use App\Services\ApplicationTracker;
use App\Services\RequiredOjtHoursAccessor;
use App\Support\StudentYearLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HoursAgreementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{
     *   schoolUser: User,
     *   school: School,
     *   hostUser: User,
     *   company: Company,
     *   studentUser: User,
     *   student: Student,
     *   internship: Internship
     * }
     */
    private function createContext(string $hostType = 'company', ?int $hostHours = 300, int $schoolHours = 486): array
    {
        foreach (['org.manage_students', 'manage_users', 'org.manage_members', 'org.approve_applications', 'org.manage_internships'] as $key) {
            Permission::query()->updateOrCreate(['key' => $key], ['scope' => 'organization', 'description' => $key]);
        }

        $schoolUser = User::factory()->create(['role' => 'school', 'is_active' => true]);
        $subscription = Subscription::query()->create(['plan' => 'standard', 'status' => 'active', 'billing_cycle' => 'monthly']);
        $schoolOrg = Organization::query()->create([
            'name' => 'Hours School',
            'type' => 'school',
            'subscription_id' => $subscription->id,
            'owner_user_id' => $schoolUser->id,
            'is_active' => true,
        ]);
        $schoolRole = Role::query()->create([
            'tenant_id' => $schoolOrg->id,
            'name' => 'Admin',
            'slug' => 'admin_'.uniqid(),
            'scope' => 'organization',
            'organization_type' => 'school',
            'is_system' => false,
        ]);
        $schoolRole->permissions()->sync(Permission::query()->pluck('id'));
        OrganizationMembership::query()->create([
            'organization_id' => $schoolOrg->id,
            'user_id' => $schoolUser->id,
            'role_id' => $schoolRole->id,
            'status' => 'active',
        ]);
        $schoolUser->forceFill(['tenant_id' => $schoolOrg->id, 'role_id' => $schoolRole->id])->save();
        $school = School::query()->create([
            'user_id' => $schoolUser->id,
            'organization_id' => $schoolOrg->id,
            'institution_name' => 'Hours School',
            'subscription_code' => 'HS-'.uniqid(),
            'verification_status' => 'approved',
        ]);

        SchoolCourseRequirement::query()->create([
            'school_id' => $school->id,
            'course_name' => 'BSIT',
            'course_name_key' => SchoolCourseRequirement::courseNameKey('BSIT'),
            'required_hours' => $schoolHours,
            'eligible_year_levels' => StudentYearLevel::DEFAULT_ELIGIBLE,
        ]);

        $hostUser = User::factory()->create(['role' => 'company', 'is_active' => true]);
        $hostSub = Subscription::query()->create(['plan' => 'standard', 'status' => 'active', 'billing_cycle' => 'monthly']);
        $hostOrg = Organization::query()->create([
            'name' => $hostType === 'municipal_hall' ? 'Naic Hall' : 'Hours Co',
            'type' => $hostType,
            'subscription_id' => $hostSub->id,
            'owner_user_id' => $hostUser->id,
            'is_active' => true,
        ]);
        $hostRole = Role::query()->create([
            'tenant_id' => $hostOrg->id,
            'name' => 'Host Admin',
            'slug' => 'host_'.uniqid(),
            'scope' => 'organization',
            'organization_type' => $hostType,
            'is_system' => false,
        ]);
        $hostRole->permissions()->sync(Permission::query()->pluck('id'));
        OrganizationMembership::query()->create([
            'organization_id' => $hostOrg->id,
            'user_id' => $hostUser->id,
            'role_id' => $hostRole->id,
            'status' => 'active',
        ]);
        $hostUser->forceFill(['tenant_id' => $hostOrg->id, 'role_id' => $hostRole->id])->save();
        $company = Company::query()->create([
            'user_id' => $hostUser->id,
            'organization_id' => $hostOrg->id,
            'company_name' => $hostOrg->name,
            'verification_status' => 'approved',
        ]);

        Contract::query()->create([
            'school_user_id' => $schoolUser->id,
            'school_name' => $school->institution_name,
            'company_user_id' => $hostUser->id,
            'company_name' => $company->company_name,
            'requested_by_role' => 'school',
            'requested_by_user_id' => $schoolUser->id,
            'partner_user_id' => $hostUser->id,
            'status' => 'active',
            'subject' => 'Hours MOA',
        ]);

        $internship = Internship::query()->create([
            'company_id' => $company->id,
            'company_name' => $company->company_name,
            'host_type' => $hostType,
            'host_name' => $company->company_name,
            'title' => 'Hours Role',
            'status' => 'active',
            'approval_status' => 'approved',
            'slots_available' => 2,
            'host_required_hours' => $hostHours,
            'required_documents' => ['Endorsement'],
            'requires_resume' => false,
        ]);

        $studentUser = User::factory()->create(['role' => 'student', 'is_active' => true, 'name' => 'Hours Intern']);
        $student = Student::query()->create([
            'user_id' => $studentUser->id,
            'school_id' => $school->id,
            'organization_id' => $schoolOrg->id,
            'school_name' => $school->institution_name,
            'course' => 'BSIT',
            'year_level' => '4th Year',
        ]);

        return compact('schoolUser', 'school', 'hostUser', 'company', 'studentUser', 'student', 'internship');
    }

    public function test_optional_host_hours_saved_and_validated(): void
    {
        $ctx = $this->createContext('company', null);
        Sanctum::actingAs($ctx['hostUser']);

        $created = $this->postJson('/api/internships', [
            'title' => 'Optional Hours Job',
            'status' => 'active',
            'host_required_hours' => 400,
        ])->assertCreated();
        $created->assertJsonPath('data.hostRequiredHours', 400);

        $this->postJson('/api/internships', [
            'title' => 'Bad Hours',
            'status' => 'active',
            'host_required_hours' => 0,
        ])->assertStatus(422);

        $this->postJson('/api/internships', [
            'title' => 'Too High',
            'status' => 'active',
            'host_required_hours' => 10001,
        ])->assertStatus(422);

        $blank = $this->postJson('/api/internships', [
            'title' => 'No Host Hours',
            'status' => 'active',
        ])->assertCreated();
        $blank->assertJsonPath('data.hostRequiredHours', null);
    }

    /**
     * @dataProvider hostTypes
     */
    public function test_mismatch_creates_agreement_and_accept_requires_agreed(string $hostType): void
    {
        $ctx = $this->createContext($hostType, 300, 486);
        Sanctum::actingAs($ctx['studentUser']);
        $appId = $this->postApplication($ctx['internship']->id, [], $ctx['studentUser'])
            ->assertCreated()
            ->json('data.id');

        $appPayload = $this->getJson('/api/applications')->json('data.0');
        $this->assertTrue($appPayload['hoursMismatch']);
        $this->assertSame(486, $appPayload['schoolRequiredHours']);
        $this->assertSame(300, $appPayload['hostRequiredHours']);
        $this->assertSame('pending', $appPayload['hoursAgreement']['status']);

        // Accept blocked while pending.
        $application = Application::query()->findOrFail($appId);
        $application->forceFill(['tracker_stage' => ApplicationTracker::STAGE_ASSESSED, 'status' => 'endorsed'])->save();
        Sanctum::actingAs($ctx['hostUser']);
        $this->postJson('/api/applications/'.$appId.'/tracker/transition', [
            'to_stage' => ApplicationTracker::STAGE_ACCEPTED,
        ])->assertStatus(422);

        // School proposes school hours; host confirms → agreed.
        Sanctum::actingAs($ctx['schoolUser']);
        $this->postJson('/api/applications/'.$appId.'/hours-agreement/propose', [
            'source' => 'school',
            'hours' => 486,
        ])->assertOk();

        Sanctum::actingAs($ctx['hostUser']);
        $this->postJson('/api/applications/'.$appId.'/hours-agreement/confirm')->assertOk()
            ->assertJsonPath('data.agreement.status', 'agreed')
            ->assertJsonPath('data.agreement.agreedHours', 486);

        $application = $application->fresh();
        $this->assertSame(486, (int) $application->required_ojt_hours);
        $this->assertSame(486, app(RequiredOjtHoursAccessor::class)->forApplication($application));

        // Course hours unchanged.
        $this->assertSame(486, (int) SchoolCourseRequirement::query()->where('school_id', $ctx['school']->id)->value('required_hours'));

        // Accept now allowed.
        $this->postJson('/api/applications/'.$appId.'/tracker/transition', [
            'to_stage' => ApplicationTracker::STAGE_ACCEPTED,
        ])->assertOk();
    }

    public function test_no_mismatch_when_blank_or_equal_and_accept_allowed(): void
    {
        $blank = $this->createContext('company', null, 486);
        Sanctum::actingAs($blank['studentUser']);
        $id = $this->postApplication($blank['internship']->id, [], $blank['studentUser'])->json('data.id');
        $this->assertNull(ApplicationHoursAgreement::query()->where('application_id', $id)->first());
        $this->assertFalse($this->getJson('/api/applications')->json('data.0.hoursMismatch'));

        $equal = $this->createContext('municipal_hall', 486, 486);
        Sanctum::actingAs($equal['studentUser']);
        $id2 = $this->postApplication($equal['internship']->id, [], $equal['studentUser'])->json('data.id');
        $this->assertNull(ApplicationHoursAgreement::query()->where('application_id', $id2)->first());

        Application::query()->findOrFail($id2)->forceFill([
            'tracker_stage' => ApplicationTracker::STAGE_ASSESSED,
            'status' => 'endorsed',
        ])->save();
        Sanctum::actingAs($equal['hostUser']);
        $this->postJson('/api/applications/'.$id2.'/tracker/transition', [
            'to_stage' => ApplicationTracker::STAGE_ACCEPTED,
        ])->assertOk();
    }

    public function test_propose_change_resets_other_confirmation(): void
    {
        $ctx = $this->createContext('company', 200, 500);
        Sanctum::actingAs($ctx['studentUser']);
        $appId = $this->postApplication($ctx['internship']->id, [], $ctx['studentUser'])->json('data.id');

        Sanctum::actingAs($ctx['schoolUser']);
        $this->postJson('/api/applications/'.$appId.'/hours-agreement/propose', [
            'hours' => 500,
            'source' => 'school',
        ])->assertOk();

        Sanctum::actingAs($ctx['hostUser']);
        $this->postJson('/api/applications/'.$appId.'/hours-agreement/propose', [
            'hours' => 350,
            'source' => 'custom',
            'note' => 'Compromise',
        ])->assertOk()
            ->assertJsonPath('data.agreement.hostConfirmed', true)
            ->assertJsonPath('data.agreement.schoolConfirmed', false)
            ->assertJsonPath('data.agreement.proposedHours', 350);
    }

    public function test_student_cannot_mutate_and_cross_org_forbidden(): void
    {
        $ctx = $this->createContext('company', 200, 500);
        Sanctum::actingAs($ctx['studentUser']);
        $appId = $this->postApplication($ctx['internship']->id, [], $ctx['studentUser'])->json('data.id');

        $this->getJson('/api/applications/'.$appId.'/hours-agreement')->assertOk();
        $this->postJson('/api/applications/'.$appId.'/hours-agreement/propose', ['hours' => 400])->assertForbidden();
        $this->postJson('/api/applications/'.$appId.'/hours-agreement/confirm')->assertForbidden();

        $other = $this->createContext('company', 100, 400);
        Sanctum::actingAs($other['schoolUser']);
        $this->getJson('/api/applications/'.$appId.'/hours-agreement')->assertForbidden();
        Sanctum::actingAs($other['hostUser']);
        $this->postJson('/api/applications/'.$appId.'/hours-agreement/propose', ['hours' => 100])->assertForbidden();
    }

    public function test_post_first_dtr_change_requires_reason(): void
    {
        $ctx = $this->createContext('company', 200, 500);
        Sanctum::actingAs($ctx['studentUser']);
        $appId = $this->postApplication($ctx['internship']->id, [], $ctx['studentUser'])->json('data.id');

        Sanctum::actingAs($ctx['schoolUser']);
        $this->postJson('/api/applications/'.$appId.'/hours-agreement/propose', ['hours' => 400])->assertOk();
        Sanctum::actingAs($ctx['hostUser']);
        $this->postJson('/api/applications/'.$appId.'/hours-agreement/confirm')->assertOk();

        OjtDtrSubmission::query()->create([
            'application_id' => $appId,
            'student_id' => $ctx['student']->id,
            'company_id' => $ctx['company']->id,
            'period_start' => now()->subWeek()->toDateString(),
            'period_end' => now()->toDateString(),
            'submitted_hours' => 40,
            'status' => 'approved',
            'submitted_by_user_id' => $ctx['studentUser']->id,
            'file_path' => 'dtr/test.pdf',
            'file_name' => 'test.pdf',
            'file_mime' => 'application/pdf',
            'file_size' => 100,
        ]);

        Sanctum::actingAs($ctx['schoolUser']);
        $this->postJson('/api/applications/'.$appId.'/hours-agreement/propose', [
            'hours' => 450,
        ])->assertStatus(422);

        $this->postJson('/api/applications/'.$appId.'/hours-agreement/propose', [
            'hours' => 450,
            'reason' => 'Program extended by CHED guidance',
        ])->assertOk()->assertJsonPath('data.agreement.status', 'pending');
    }

    /**
     * B+D: course without hours cannot apply even when the posting has host hours.
     *
     * @dataProvider hostTypes
     */
    public function test_phase_b_no_hours_still_blocks_apply(string $hostType): void
    {
        $ctx = $this->createContext($hostType, 300, 486);
        SchoolCourseRequirement::query()->where('school_id', $ctx['school']->id)->delete();
        $ctx['student']->forceFill(['course' => 'Unconfigured Course'])->save();

        Sanctum::actingAs($ctx['studentUser']);
        $this->postJson('/api/applications', [
            'internship_id' => $ctx['internship']->id,
            'endorsement_url' => $this->seedEndorsementDocument($ctx['studentUser'])->file_url,
        ])->assertStatus(422)->assertJsonPath('code', 'course_setup_incomplete');
    }

    public function test_agreed_hours_do_not_change_other_students(): void
    {
        $ctx = $this->createContext('company', 200, 500);
        Sanctum::actingAs($ctx['studentUser']);
        $appId = $this->postApplication($ctx['internship']->id, [], $ctx['studentUser'])->json('data.id');

        $otherUser = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $other = Student::query()->create([
            'user_id' => $otherUser->id,
            'school_id' => $ctx['school']->id,
            'course' => 'BSIT',
            'year_level' => '4th Year',
            'school_name' => $ctx['school']->institution_name,
        ]);
        $otherApp = Application::query()->create([
            'internship_id' => $ctx['internship']->id,
            'student_id' => $other->id,
            'company_id' => $ctx['company']->id,
            'status' => 'submitted',
            'tracker_stage' => ApplicationTracker::STAGE_APPLIED,
            'internship_title' => 'Hours Role',
            'student_name' => 'Other',
            'student_email' => $otherUser->email,
            'student_course' => 'BSIT',
            'required_ojt_hours' => 500,
        ]);

        Sanctum::actingAs($ctx['schoolUser']);
        $this->postJson('/api/applications/'.$appId.'/hours-agreement/propose', ['hours' => 350])->assertOk();
        Sanctum::actingAs($ctx['hostUser']);
        $this->postJson('/api/applications/'.$appId.'/hours-agreement/confirm')->assertOk();

        $this->assertSame(350, (int) Application::query()->find($appId)->required_ojt_hours);
        $this->assertSame(500, (int) $otherApp->fresh()->required_ojt_hours);
        $this->assertSame(500, (int) SchoolCourseRequirement::query()->where('school_id', $ctx['school']->id)->value('required_hours'));
    }

    public static function hostTypes(): array
    {
        return [
            'company' => ['company'],
            'municipal hall' => ['municipal_hall'],
        ];
    }
}
