<?php

namespace Tests\Feature;

use App\Models\Application;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Full host placement API chain for Company and Municipal Hall (no pre-seeded accepted apps).
 */
class HostPlacementLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_host_full_placement_lifecycle(): void
    {
        $this->runLifecycleForHostType('company');
    }

    public function test_municipal_hall_host_full_placement_lifecycle(): void
    {
        $this->runLifecycleForHostType('municipal_hall');
    }

    private function runLifecycleForHostType(string $hostOrgType): void
    {
        $ctx = $this->createLinkedContext($hostOrgType);
        $otherHost = $this->createOtherHost($hostOrgType === 'municipal_hall' ? 'company' : 'municipal_hall');

        // 1) Host posts job
        Sanctum::actingAs($ctx['hostUser']);
        $job = $this->postJson('/api/internships', [
            'title' => 'Lifecycle '.$hostOrgType.' Role',
            'description' => 'Full chain test',
            'status' => 'active',
            'requires_resume' => false,
            'slots_available' => 2,
        ]);
        $job->assertCreated();
        $internshipId = (int) $job->json('data.id');
        $this->assertDatabaseHas('internships', [
            'id' => $internshipId,
            'company_id' => $ctx['company']->id,
            'title' => 'Lifecycle '.$hostOrgType.' Role',
        ]);

        // Wrong org cannot manage this posting's applications later.
        // 2) Student applies
        Sanctum::actingAs($ctx['studentUser']);
        $apply = $this->postApplication($internshipId);
        $apply->assertCreated();
        $applicationId = (int) $apply->json('data.id');
        $this->assertDatabaseHas('applications', [
            'id' => $applicationId,
            'status' => 'submitted',
            'company_id' => $ctx['company']->id,
        ]);
        $this->assertSame(
            486,
            (int) Application::query()->find($applicationId)?->required_ojt_hours,
            'Required hours must come from school course requirement (BSIT=486), not host org'
        );

        // 3) School endorses (other school forbidden)
        Sanctum::actingAs($otherHost['user']);
        $this->patchJson('/api/applications/'.$applicationId.'/status', [
            'status' => 'endorsed',
        ])->assertForbidden();

        Sanctum::actingAs($ctx['schoolUser']);
        $endorse = $this->patchJson('/api/applications/'.$applicationId.'/status', [
            'status' => 'endorsed',
        ]);
        $endorse->assertOk();
        $this->assertSame('endorsed', Application::query()->find($applicationId)?->status);

        // 4) Host schedules interview
        Sanctum::actingAs($ctx['hostUser']);
        $interview = $this->postJson('/api/interviews', [
            'application_id' => $applicationId,
            'scheduled_at' => now()->addDays(2)->toIso8601String(),
            'duration_minutes' => 45,
            'mode' => 'onsite',
            'location_or_link' => 'Hall Conference Room',
        ]);
        $interview->assertCreated();
        $interviewId = (int) $interview->json('data.id');
        $this->assertDatabaseHas('application_interviews', [
            'id' => $interviewId,
            'application_id' => $applicationId,
            'status' => 'proposed',
        ]);

        Sanctum::actingAs($ctx['studentUser']);
        $this->patchJson('/api/interviews/'.$interviewId.'/confirm')->assertOk();
        $this->assertDatabaseHas('application_interviews', [
            'id' => $interviewId,
            'status' => 'confirmed',
        ]);

        // 5) Mark interviewed -> assess -> accept (tracker-enforced)
        Sanctum::actingAs($ctx['hostUser']);
        $this->postJson('/api/applications/'.$applicationId.'/tracker/transition', [
            'to_stage' => 'interviewed',
        ])->assertOk();

        $assessment = $this->postJson('/api/applications/'.$applicationId.'/tracker/assessment', [
            'technical' => 4,
            'communication' => 5,
            'attitude' => 4,
            'comments' => 'Ready for placement',
        ]);
        $assessment->assertCreated();

        Sanctum::actingAs($otherHost['user']);
        $this->postJson('/api/applications/'.$applicationId.'/tracker/transition', [
            'to_stage' => 'accepted',
        ])->assertForbidden();

        Sanctum::actingAs($ctx['hostUser']);
        $accept = $this->postJson('/api/applications/'.$applicationId.'/tracker/transition', [
            'to_stage' => 'accepted',
        ]);
        $accept->assertOk();
        $this->assertSame('accepted', Application::query()->find($applicationId)?->status);
        $this->assertSame('accepted', Application::query()->find($applicationId)?->tracker_stage);

        // 6) DTR submit -> host reject with remarks -> resubmit -> host approve -> coordinator approve
        Sanctum::actingAs($ctx['studentUser']);
        $upload = $this->post('/api/ojt-dtrs', [
            'application_id' => $applicationId,
            'period_start' => '2026-10-01',
            'period_end' => '2026-10-07',
            'submitted_hours' => 8,
            'file' => UploadedFile::fake()->create('week1.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json']);
        $upload->assertCreated();
        $upload->assertJsonPath('data.status', 'submitted');
        $upload->assertJsonPath('data.reviewStage', 'host');
        $dtrId = (int) $upload->json('data.id');

        Sanctum::actingAs($otherHost['user']);
        $this->postJson('/api/ojt-dtrs/'.$dtrId.'/approve')->assertForbidden();

        Sanctum::actingAs($ctx['hostUser']);
        $reject = $this->postJson('/api/ojt-dtrs/'.$dtrId.'/reject', [
            'reason' => 'Incomplete signatures',
            'revision_required' => true,
        ]);
        $reject->assertOk();
        $this->assertSame('rejected', OjtDtrSubmission::query()->find($dtrId)?->status);
        $this->assertSame('Incomplete signatures', OjtDtrSubmission::query()->find($dtrId)?->company_rejection_reason);

        $resubmit = $this->post('/api/ojt-dtrs/'.$dtrId.'/resubmit', [
            'application_id' => $applicationId,
            'period_start' => '2026-10-01',
            'period_end' => '2026-10-07',
            'submitted_hours' => 8,
            'file' => UploadedFile::fake()->create('week1-v2.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json']);
        $resubmit->assertCreated();
        $newDtrId = (int) $resubmit->json('data.id');
        $this->assertSame('approved', OjtDtrSubmission::query()->find($newDtrId)?->status);
        $this->assertNull(OjtDtrSubmission::query()->find($newDtrId)?->review_stage);

        Sanctum::actingAs($ctx['schoolUser']);
        $this->postJson('/api/ojt-dtrs/'.$newDtrId.'/approve')->assertStatus(422);
        $this->assertSame('approved', OjtDtrSubmission::query()->find($newDtrId)?->status);

        $this->assertSame(1, OjtDtrSubmission::query()->where('status', 'approved')->count());
        $this->assertEquals(
            8.0,
            (float) OjtDtrSubmission::query()->where('status', 'approved')->sum('submitted_hours')
        );
        $this->assertSame('rejected', OjtDtrSubmission::query()->find($dtrId)?->status);
    }

    /**
     * @return array<string, mixed>
     */
    private function createLinkedContext(string $hostOrgType): array
    {
        foreach ([
            'org.manage_internships',
            'org.approve_applications',
            'org.manage_applications',
            'org.view_applications',
            'org.approve_ojt_logs',
            'org.manage_assessments',
            'org.manage_students',
        ] as $key) {
            Permission::query()->updateOrCreate(
                ['key' => $key],
                ['scope' => 'organization', 'description' => $key]
            );
        }

        $schoolUser = User::factory()->create(['role' => 'school', 'is_active' => true]);
        $schoolOrg = $this->makeOrg('Lifecycle School', 'school', $schoolUser);
        $schoolRole = $this->makeRole($schoolOrg, 'school', [
            'org.approve_applications',
            'org.manage_applications',
            'org.view_applications',
            'org.approve_ojt_logs',
            'org.manage_students',
        ]);
        $this->join($schoolOrg, $schoolUser, $schoolRole);
        $school = School::query()->create([
            'user_id' => $schoolUser->id,
            'organization_id' => $schoolOrg->id,
            'institution_name' => 'Lifecycle School',
            'subscription_code' => 'LIF-'.strtoupper(substr(uniqid(), -4)),
            'verification_status' => 'approved',
        ]);
        SchoolCourseRequirement::query()->create([
            'school_id' => $school->id,
            'course_name' => 'BSIT',
            'course_name_key' => SchoolCourseRequirement::courseNameKey('BSIT'),
            'required_hours' => 486,
        ]);

        $hostUser = User::factory()->create([
            'role' => $hostOrgType === 'municipal_hall' ? 'municipal_hall' : 'company',
            'is_active' => true,
        ]);
        $hostOrg = $this->makeOrg(
            $hostOrgType === 'municipal_hall' ? 'Lifecycle Municipal Hall' : 'Lifecycle Company',
            $hostOrgType,
            $hostUser
        );
        $hostRole = $this->makeRole($hostOrg, $hostOrgType, [
            'org.manage_internships',
            'org.approve_applications',
            'org.manage_applications',
            'org.view_applications',
            'org.approve_ojt_logs',
            'org.manage_assessments',
        ], 'company_admin');
        $this->join($hostOrg, $hostUser, $hostRole);
        $company = Company::query()->create([
            'user_id' => $hostUser->id,
            'organization_id' => $hostOrg->id,
            'company_name' => $hostOrg->name,
            'company_type' => $hostOrgType === 'municipal_hall' ? 'municipal_hall' : null,
            'lgu_type' => $hostOrgType === 'municipal_hall' ? 'municipality' : null,
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
            'subject' => 'Lifecycle MOA',
        ]);

        $studentUser = User::factory()->create([
            'role' => 'student',
            'name' => 'Lifecycle Intern',
            'is_active' => true,
        ]);
        $student = Student::query()->create([
            'user_id' => $studentUser->id,
            'school_id' => $school->id,
            'organization_id' => $schoolOrg->id,
            'school_name' => $school->institution_name,
            'school_subscription_code' => $school->subscription_code,
            'course' => 'BSIT',
            'year_level' => '4th Year',
        ]);

        return compact('schoolUser', 'school', 'hostUser', 'company', 'studentUser', 'student');
    }

    /**
     * @return array{user: User}
     */
    private function createOtherHost(string $type): array
    {
        foreach (['org.approve_applications', 'org.approve_ojt_logs', 'org.manage_internships'] as $key) {
            Permission::query()->updateOrCreate(
                ['key' => $key],
                ['scope' => 'organization', 'description' => $key]
            );
        }
        $user = User::factory()->create([
            'role' => $type === 'municipal_hall' ? 'municipal_hall' : 'company',
            'is_active' => true,
        ]);
        $org = $this->makeOrg('Other Host '.$type, $type, $user);
        $role = $this->makeRole($org, $type, [
            'org.approve_applications',
            'org.approve_ojt_logs',
            'org.manage_internships',
        ]);
        $this->join($org, $user, $role);
        Company::query()->create([
            'user_id' => $user->id,
            'organization_id' => $org->id,
            'company_name' => $org->name,
            'lgu_type' => $type === 'municipal_hall' ? 'city' : null,
            'verification_status' => 'approved',
        ]);

        return compact('user');
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
            'settings' => [],
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<int, string>  $keys
     */
    private function makeRole(Organization $organization, string $type, array $keys, string $slug = ''): Role
    {
        $role = Role::query()->create([
            'tenant_id' => $organization->id,
            'name' => 'Role '.uniqid(),
            'slug' => $slug !== '' ? $slug : 'role_'.uniqid(),
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
}
