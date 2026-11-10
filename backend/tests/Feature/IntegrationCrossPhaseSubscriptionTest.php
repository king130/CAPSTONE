<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Company;
use App\Models\Internship;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Permission;
use App\Models\Role;
use App\Models\School;
use App\Models\SchoolCourseRequirement;
use App\Models\Student;
use App\Models\Subscription;
use App\Models\User;
use App\Services\SubscriptionAccessService;
use App\Support\StudentYearLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Cross-phase gaps for Phase H with Company and Municipal Hall hosts.
 */
class IntegrationCrossPhaseSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{hostUser: User, hostOrg: Organization, company: Company, schoolUser: User, school: School, studentUser: User, internship: Internship, application: Application}
     */
    private function placementContext(string $hostType = 'company'): array
    {
        foreach ([
            'org.manage_internships', 'org.approve_applications', 'org.manage_applications',
            'org.manage_students', 'org.approve_ojt_logs', 'org.manage_subscription',
        ] as $key) {
            Permission::query()->updateOrCreate(['key' => $key], ['scope' => 'organization', 'description' => $key]);
        }

        $schoolUser = User::factory()->create(['role' => 'school', 'is_active' => true]);
        $schoolSub = Subscription::query()->create([
            'plan' => 'standard', 'status' => 'active', 'billing_cycle' => 'monthly',
            'ends_at' => now()->addYear()->toDateString(),
        ]);
        $schoolOrg = Organization::query()->create([
            'name' => 'X School', 'type' => 'school', 'subscription_id' => $schoolSub->id,
            'owner_user_id' => $schoolUser->id, 'is_active' => true,
        ]);
        $this->attachAdmin($schoolUser, $schoolOrg, 'school');
        $school = School::query()->create([
            'user_id' => $schoolUser->id, 'organization_id' => $schoolOrg->id,
            'institution_name' => 'X School', 'subscription_code' => 'XS-'.uniqid(),
            'verification_status' => 'approved',
        ]);
        SchoolCourseRequirement::query()->create([
            'school_id' => $school->id,
            'course_name' => 'BSIT',
            'course_name_key' => SchoolCourseRequirement::courseNameKey('BSIT'),
            'required_hours' => 486,
            'eligible_year_levels' => StudentYearLevel::DEFAULT_ELIGIBLE,
        ]);

        $hostUser = User::factory()->create([
            'role' => $hostType === 'municipal_hall' ? 'municipal_hall' : 'company',
            'is_active' => true,
        ]);
        $hostSub = Subscription::query()->create([
            'plan' => 'standard', 'status' => 'active', 'billing_cycle' => 'monthly',
            'ends_at' => now()->addYear()->toDateString(),
        ]);
        $hostOrg = Organization::query()->create([
            'name' => 'X Host '.$hostType, 'type' => $hostType, 'subscription_id' => $hostSub->id,
            'owner_user_id' => $hostUser->id, 'is_active' => true,
        ]);
        $this->attachAdmin($hostUser, $hostOrg, $hostType === 'municipal_hall' ? 'company' : 'company');
        $company = Company::query()->create([
            'user_id' => $hostUser->id, 'organization_id' => $hostOrg->id,
            'company_name' => $hostOrg->name,
            'company_type' => $hostType === 'municipal_hall' ? 'municipal_hall' : null,
            'verification_status' => 'approved',
        ]);

        $studentUser = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $student = Student::query()->create([
            'user_id' => $studentUser->id, 'school_id' => $school->id, 'organization_id' => $schoolOrg->id,
            'school_name' => 'X School', 'course' => 'BSIT', 'year_level' => '4th Year',
        ]);

        $internship = Internship::query()->create([
            'company_id' => $company->id,
            'title' => 'X Placement',
            'description' => 'desc',
            'slots' => 2,
            'status' => 'open',
            'requires_resume' => false,
            'required_documents' => ['endorsement'],
        ]);

        $application = Application::query()->create([
            'student_id' => $student->id,
            'internship_id' => $internship->id,
            'company_id' => $company->id,
            'status' => 'endorsed',
            'student_name' => $studentUser->name,
            'internship_title' => $internship->title,
            'required_ojt_hours' => 486,
        ]);

        return compact('hostUser', 'hostOrg', 'company', 'schoolUser', 'school', 'studentUser', 'internship', 'application');
    }

    private function attachAdmin(User $user, Organization $org, string $orgType): void
    {
        $role = Role::query()->create([
            'tenant_id' => $org->id,
            'name' => 'Admin',
            'slug' => 'admin_'.uniqid(),
            'scope' => 'organization',
            'organization_type' => $orgType,
            'is_system' => false,
        ]);
        $role->permissions()->sync(Permission::query()->pluck('id'));
        OrganizationMembership::query()->create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);
        $user->forceFill(['tenant_id' => $org->id, 'role_id' => $role->id])->save();
    }

    public function test_grace_keeps_write_access_for_company_and_municipal(): void
    {
        foreach (['company', 'municipal_hall'] as $type) {
            $ctx = $this->placementContext($type);
            app(SubscriptionAccessService::class)->forceGrace(
                $ctx['hostOrg']->subscription,
                $ctx['hostOrg'],
                $ctx['hostUser'],
                'integration grace'
            );
            Sanctum::actingAs($ctx['hostUser']);
            $state = app(SubscriptionAccessService::class)->resolve($ctx['hostOrg']->fresh('subscription'));
            $this->assertSame('past_due', $state['status'], $type);
            $this->assertTrue($state['canWrite'], $type);

            $this->patchJson('/api/internships/'.$ctx['internship']->id, [
                'title' => 'Grace still writable '.$type,
            ])->assertOk();
        }
    }

    public function test_expired_blocks_host_writes_but_allows_reads_company_and_municipal(): void
    {
        foreach (['company', 'municipal_hall'] as $type) {
            $ctx = $this->placementContext($type);
            app(SubscriptionAccessService::class)->forceExpired(
                $ctx['hostOrg']->subscription,
                $ctx['hostOrg'],
                $ctx['hostUser'],
                'integration expire'
            );
            Sanctum::actingAs($ctx['hostUser']);

            $this->postJson('/api/internships', [
                'title' => 'Blocked', 'description' => 'x', 'slots' => 1,
            ])->assertStatus(402)->assertJsonPath('code', 'subscription_expired');

            $this->patchJson('/api/internships/'.$ctx['internship']->id, [
                'title' => 'Blocked edit',
            ])->assertStatus(402)->assertJsonPath('code', 'subscription_expired');

            $this->postJson('/api/interviews', [
                'application_id' => $ctx['application']->id,
                'scheduled_at' => now()->addDay()->toIso8601String(),
                'mode' => 'online',
            ])->assertStatus(402)->assertJsonPath('code', 'subscription_expired');

            $this->patchJson('/api/applications/'.$ctx['application']->id.'/status', [
                'status' => 'accepted',
            ])->assertStatus(402)->assertJsonPath('code', 'subscription_expired');

            $this->getJson('/api/internships/'.$ctx['internship']->id)->assertOk();
            $this->getJson('/api/applications')->assertOk();
            $this->getJson('/api/auth/me')->assertOk()
                ->assertJsonPath('subscriptionAccess.status', 'expired');
        }
    }

    public function test_expired_host_dtr_review_blocked_student_submit_allowed(): void
    {
        Storage::fake('local');
        $ctx = $this->placementContext('company');
        $ctx['application']->forceFill(['status' => 'accepted'])->save();

        app(SubscriptionAccessService::class)->forceExpired(
            $ctx['hostOrg']->subscription,
            $ctx['hostOrg'],
            $ctx['hostUser'],
            'expire for dtr'
        );

        Sanctum::actingAs($ctx['studentUser']);
        $submit = $this->post('/api/ojt-dtrs', [
            'application_id' => $ctx['application']->id,
            'work_date' => now()->toDateString(),
            'hours' => 8,
            'file' => UploadedFile::fake()->create('dtr.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json']);
        // Student role bypasses subscription.write — should not be 402.
        $this->assertNotEquals(402, $submit->status(), (string) $submit->getContent());

        if ($submit->status() >= 200 && $submit->status() < 300) {
            $dtrId = (int) ($submit->json('data.id') ?? $submit->json('id') ?? 0);
            Sanctum::actingAs($ctx['hostUser']);
            if ($dtrId > 0) {
                $this->postJson('/api/ojt-dtrs/'.$dtrId.'/approve')->assertStatus(402)
                    ->assertJsonPath('code', 'subscription_expired');
                $this->postJson('/api/ojt-dtrs/'.$dtrId.'/reject', ['reason' => 'nope'])->assertStatus(402)
                    ->assertJsonPath('code', 'subscription_expired');
            }
        }
    }

    public function test_verification_upload_allowed_on_trial_and_expired(): void
    {
        Storage::fake('local');
        foreach (['trial', 'expired'] as $mode) {
            $user = User::factory()->create(['role' => 'company', 'is_active' => true]);
            $sub = Subscription::query()->create([
                'plan' => 'standard', 'status' => 'trialing', 'billing_cycle' => 'monthly',
            ]);
            $org = Organization::query()->create([
                'name' => 'Verify '.$mode, 'type' => 'company', 'subscription_id' => $sub->id,
                'owner_user_id' => $user->id, 'is_active' => true,
            ]);
            $access = app(SubscriptionAccessService::class);
            if ($mode === 'trial') {
                $access->startTrial($sub, $org, $user);
            } else {
                $access->startTrial($sub, $org, $user);
                $access->forceExpired($sub->fresh(), $org, $user, 'expire verify');
            }
            $this->attachAdmin($user, $org, 'company');
            Company::query()->create([
                'user_id' => $user->id, 'organization_id' => $org->id,
                'company_name' => $org->name, 'verification_status' => 'pending',
            ]);

            Sanctum::actingAs($user);
            // Verification routes are OUTSIDE subscription.write / org.verified — must not 402.
            $response = $this->post('/api/organization-verification/documents', [
                'file' => UploadedFile::fake()->create('permit.pdf', 120, 'application/pdf'),
                'label' => 'Business Permit',
            ], ['Accept' => 'application/json']);
            $this->assertNotEquals(402, $response->status(), $mode.' '.$response->getContent());
        }
    }

    public function test_school_endorse_blocked_when_school_subscription_expired(): void
    {
        $ctx = $this->placementContext('company');
        $ctx['application']->forceFill(['status' => 'pending'])->save();
        $schoolOrg = Organization::query()->find($ctx['school']->organization_id);
        app(SubscriptionAccessService::class)->forceExpired(
            $schoolOrg->subscription,
            $schoolOrg,
            $ctx['schoolUser'],
            'school expire'
        );

        Sanctum::actingAs($ctx['schoolUser']);
        $this->patchJson('/api/applications/'.$ctx['application']->id.'/status', [
            'status' => 'endorsed',
        ])->assertStatus(402)->assertJsonPath('code', 'subscription_expired');
    }
}
