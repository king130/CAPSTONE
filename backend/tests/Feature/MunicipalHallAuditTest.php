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
use App\Support\CaviteAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Final verification audit coverage for municipal_hall (API-level).
 */
class MunicipalHallAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_municipal_hall_cannot_create_internship(): void
    {
        $ctx = $this->createMunicipalHall('pending');
        Sanctum::actingAs($ctx['user']);

        $this->postJson('/api/internships', [
            'title' => 'LGU IT Support',
            'description' => 'OJT at municipal hall',
            'status' => 'active',
        ])->assertForbidden();
    }

    public function test_approved_municipal_hall_reuses_company_workspace_and_can_post_job(): void
    {
        $ctx = $this->createMunicipalHall('approved');
        Sanctum::actingAs($ctx['user']);

        $me = $this->getJson('/api/auth/me');
        $me->assertOk();
        $me->assertJsonPath('role', 'company');
        $me->assertJsonPath('activeOrganization.type', 'municipal_hall');
        $me->assertJsonPath('verificationStatus', 'approved');

        $created = $this->postJson('/api/internships', [
            'title' => 'Municipal Records Assistant',
            'description' => 'OJT placement at LGU',
            'status' => 'active',
            'slots' => 2,
        ]);
        $created->assertCreated();
        $this->assertDatabaseHas('internships', [
            'company_id' => $ctx['company']->id,
            'title' => 'Municipal Records Assistant',
        ]);
    }

    public function test_verified_staff_never_see_awaiting_verification(): void
    {
        $ctx = $this->createMunicipalHall('approved');
        $staff = $this->addStaff($ctx['organization'], $ctx['adminRole'], 'staff.lgu@example.com');

        Sanctum::actingAs($staff);
        $me = $this->getJson('/api/auth/me');
        $me->assertOk();
        $me->assertJsonPath('role', 'company');
        $me->assertJsonPath('activeOrganization.type', 'municipal_hall');
        $me->assertJsonPath('verificationStatus', 'approved');
    }

    public function test_staff_without_manage_profile_cannot_edit_org_settings(): void
    {
        $ctx = $this->createMunicipalHall('approved');
        $hrRole = $this->makeRole($ctx['organization'], 'municipal_hall', [
            'org.approve_ojt_logs',
            'org.view_applications',
        ], 'hr');
        $staff = $this->addStaff($ctx['organization'], $hrRole, 'hr.lgu@example.com');

        Sanctum::actingAs($staff);
        $this->patchJson('/api/profile', [
            'profile' => [
                'officialName' => 'Hacked Rename',
                'companyName' => 'Hacked Rename',
            ],
        ])->assertUnprocessable();

        $this->assertSame(
            'Municipality of Audit — Municipal Hall',
            $ctx['company']->fresh()->company_name
        );
    }

    public function test_cross_organization_dtr_approval_is_forbidden(): void
    {
        $lifecycle = $this->createAcceptedPlacementLifecycle();
        $other = $this->createMunicipalHall('approved', 'Municipality of Other — Municipal Hall', 'Kawit');

        Sanctum::actingAs($lifecycle['studentUser']);
        $upload = $this->post('/api/ojt-dtrs', [
            'application_id' => $lifecycle['application']->id,
            'period_start' => '2026-10-01',
            'period_end' => '2026-10-07',
            'submitted_hours' => 4,
            'file' => UploadedFile::fake()->create('dtr.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json']);
        $upload->assertCreated();
        $dtrId = (int) $upload->json('data.id');

        Sanctum::actingAs($other['user']);
        $this->postJson('/api/ojt-dtrs/'.$dtrId.'/approve')->assertForbidden();
        $this->postJson('/api/ojt-dtrs/'.$dtrId.'/reject', [
            'reason' => 'Not our intern',
            'status' => 'rejected',
        ])->assertForbidden();

        $this->assertSame('submitted', OjtDtrSubmission::query()->find($dtrId)?->status);
        $this->assertSame('host', OjtDtrSubmission::query()->find($dtrId)?->review_stage);
    }

    public function test_company_cannot_approve_municipal_hall_dtr_and_vice_versa(): void
    {
        $lifecycle = $this->createAcceptedPlacementLifecycle();
        $company = $this->createCompanyHost('approved');

        Sanctum::actingAs($lifecycle['studentUser']);
        $upload = $this->post('/api/ojt-dtrs', [
            'application_id' => $lifecycle['application']->id,
            'period_start' => '2026-10-01',
            'period_end' => '2026-10-07',
            'submitted_hours' => 5,
            'file' => UploadedFile::fake()->create('dtr2.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json']);
        $upload->assertCreated();
        $dtrId = (int) $upload->json('data.id');

        Sanctum::actingAs($company['user']);
        $this->postJson('/api/ojt-dtrs/'.$dtrId.'/approve')->assertForbidden();

        // Municipal Hall own approval then company still cannot touch.
        Sanctum::actingAs($lifecycle['hostUser']);
        $this->postJson('/api/ojt-dtrs/'.$dtrId.'/approve')->assertOk();
        $this->assertSame('submitted', OjtDtrSubmission::query()->find($dtrId)?->status);
        $this->assertSame('school', OjtDtrSubmission::query()->find($dtrId)?->review_stage);

        Sanctum::actingAs($company['user']);
        $this->postJson('/api/ojt-dtrs/'.$dtrId.'/reject', [
            'reason' => 'cross org',
            'status' => 'rejected',
        ])->assertForbidden();
    }

    public function test_full_dtr_lifecycle_with_reject_remarks_and_no_double_hours(): void
    {
        $lifecycle = $this->createAcceptedPlacementLifecycle();

        Sanctum::actingAs($lifecycle['studentUser']);
        $upload = $this->post('/api/ojt-dtrs', [
            'application_id' => $lifecycle['application']->id,
            'period_start' => '2026-10-01',
            'period_end' => '2026-10-07',
            'submitted_hours' => 8,
            'file' => UploadedFile::fake()->create('week.pdf', 120, 'application/pdf'),
        ], ['Accept' => 'application/json']);
        $upload->assertCreated();
        $upload->assertJsonPath('data.status', 'submitted');
        $upload->assertJsonPath('data.reviewStage', 'host');
        $dtrId = (int) $upload->json('data.id');

        Sanctum::actingAs($lifecycle['hostUser']);
        $reject = $this->postJson('/api/ojt-dtrs/'.$dtrId.'/reject', [
            'reason' => 'Missing supervisor signature',
            'revision_required' => true,
        ]);
        $reject->assertOk();
        $this->assertSame('revision_required', OjtDtrSubmission::query()->find($dtrId)?->status);
        $this->assertSame(
            'Missing supervisor signature',
            OjtDtrSubmission::query()->find($dtrId)?->company_rejection_reason
        );

        Sanctum::actingAs($lifecycle['hostUser']);
        $resubmit = $this->post('/api/ojt-dtrs/'.$dtrId.'/resubmit', [
            'application_id' => $lifecycle['application']->id,
            'period_start' => '2026-10-01',
            'period_end' => '2026-10-07',
            'submitted_hours' => 8,
            'remarks' => 'Corrected signature',
            'file' => UploadedFile::fake()->create('week-v2.pdf', 120, 'application/pdf'),
        ], ['Accept' => 'application/json']);
        $resubmit->assertCreated();
        $newId = (int) $resubmit->json('data.id');
        // Host-submitted resubmit skips to coordinator stage.
        $this->assertSame('submitted', OjtDtrSubmission::query()->find($newId)?->status);
        $this->assertSame('school', OjtDtrSubmission::query()->find($newId)?->review_stage);

        Sanctum::actingAs($lifecycle['schoolUser']);
        $this->postJson('/api/ojt-dtrs/'.$newId.'/approve')->assertOk();
        $row = OjtDtrSubmission::query()->find($newId);
        $this->assertSame('approved', $row?->status);
        $this->assertSame(8.0, (float) $row?->submitted_hours);

        // Prior revision row stays non-approved; hours counted once.
        $this->assertSame('revision_required', OjtDtrSubmission::query()->find($dtrId)?->status);
        $this->assertSame(1, OjtDtrSubmission::query()->where('status', 'approved')->count());
        $this->assertEquals(
            8.0,
            (float) OjtDtrSubmission::query()->where('status', 'approved')->sum('submitted_hours')
        );
    }

    public function test_second_municipal_hall_with_different_lgu_type_and_duplicate_rule(): void
    {
        $first = $this->postJson('/api/auth/register', [
            'fullName' => 'Naic Admin',
            'email' => 'naic.audit@example.com',
            'password' => 'SecurePass1',
            'role' => 'municipal_hall',
            'profile' => [
                'officialName' => 'Municipality of Naic — Municipal Hall',
                'lguType' => 'municipality',
                'representativeName' => 'Naic Admin',
                'representativePosition' => 'Municipal Administrator',
                'locationArea' => 'Naic',
                'barangay' => 'Ibayo Silangan',
                'contactNumber' => '09170000001',
            ],
        ]);
        $first->assertCreated();

        $cityHall = $this->postJson('/api/auth/register', [
            'fullName' => 'Imus City Admin',
            'email' => 'imus.audit@example.com',
            'password' => 'SecurePass1',
            'role' => 'municipal_hall',
            'profile' => [
                'officialName' => 'City Government of Imus — City Hall',
                'lguType' => 'city',
                'representativeName' => 'Imus City Admin',
                'representativePosition' => 'City Administrator',
                'locationArea' => 'Imus',
                'barangay' => 'Poblacion I-A',
                'contactNumber' => '09170000002',
            ],
        ]);
        $cityHall->assertCreated();
        $cityHall->assertJsonPath('user.activeOrganization.type', 'municipal_hall');

        $this->assertSame(2, Organization::query()->where('type', 'municipal_hall')->count());
        $this->assertSame(
            'municipality',
            Company::query()->where('company_name', 'Municipality of Naic — Municipal Hall')->value('lgu_type')
        );
        $this->assertSame(
            'city',
            Company::query()->where('company_name', 'City Government of Imus — City Hall')->value('lgu_type')
        );

        $duplicate = $this->postJson('/api/auth/register', [
            'fullName' => 'Duplicate Admin',
            'email' => 'dup.audit@example.com',
            'password' => 'SecurePass1',
            'role' => 'municipal_hall',
            'profile' => [
                'officialName' => 'Municipality of Naic — Municipal Hall',
                'lguType' => 'municipality',
                'representativeName' => 'Duplicate Admin',
                'representativePosition' => 'Staff',
                'locationArea' => 'Naic',
                'barangay' => 'Ibayo Silangan',
                'contactNumber' => '09170000003',
            ],
        ]);
        $duplicate->assertUnprocessable();
        $this->assertSame(2, Organization::query()->where('type', 'municipal_hall')->count());
    }

    public function test_cavite_address_accepts_requested_cities_without_false_rejection(): void
    {
        $inputs = [
            'Dasmariñas',
            'Dasmarinas City',
            'Imus',
            'Imus City',
            'Bacoor',
            'Bacoor City',
            'Naic',
            'Tagaytay',
            'Tagaytay City',
            'General Trias',
            'General Trias City',
            'Kawit',
        ];

        foreach ($inputs as $city) {
            $this->assertTrue(
                CaviteAddress::isKnownCity($city),
                'Expected CaviteAddress to accept: '.$city
            );
            $canonical = CaviteAddress::canonicalFromProfile([
                'locationArea' => $city,
                'barangay' => 'Poblacion',
                'companyAddress' => 'Main Road',
            ], null);
            $this->assertArrayNotHasKey('error', $canonical, 'False rejection for: '.$city);
            $this->assertNotEmpty($canonical['address'] ?? null);
        }
    }

    public function test_existing_company_and_school_rows_unchanged_by_lgu_fields(): void
    {
        $company = $this->createCompanyHost('approved');
        $this->assertNull($company['company']->lgu_type);
        $this->assertSame('company', $company['organization']->type);

        $schoolUser = User::factory()->create(['role' => 'school', 'is_active' => true]);
        $schoolOrg = $this->makeOrg('Audit School', 'school', $schoolUser);
        School::query()->create([
            'user_id' => $schoolUser->id,
            'organization_id' => $schoolOrg->id,
            'institution_name' => 'Audit School',
            'subscription_code' => 'AUD-'.strtoupper(substr(uniqid(), -4)),
            'verification_status' => 'approved',
        ]);
        $this->assertSame('school', $schoolOrg->fresh()->type);
        $this->assertSame(0, Organization::query()->where('type', 'municipal_hall')->count());
    }

    /**
     * @return array{user:User, organization:Organization, company:Company, adminRole:Role}
     */
    private function createMunicipalHall(
        string $verificationStatus,
        string $name = 'Municipality of Audit — Municipal Hall',
        string $city = 'Naic'
    ): array {
        foreach ([
            'org.manage_internships',
            'org.approve_ojt_logs',
            'org.view_applications',
            'org.approve_applications',
            'org.manage_profile',
            'manage_profile',
        ] as $key) {
            Permission::query()->updateOrCreate(
                ['key' => $key],
                ['scope' => 'organization', 'description' => $key]
            );
        }

        $user = User::factory()->create([
            'role' => 'municipal_hall',
            'email' => 'lgu.'.uniqid('', true).'@example.com',
            'is_active' => true,
            'name' => 'LGU Admin',
        ]);
        $organization = $this->makeOrg($name, 'municipal_hall', $user);
        $adminRole = $this->makeRole($organization, 'municipal_hall', [
            'org.manage_internships',
            'org.approve_ojt_logs',
            'org.view_applications',
            'org.approve_applications',
            'org.manage_profile',
            'manage_profile',
        ], 'company_admin');
        $this->join($organization, $user, $adminRole);

        $company = Company::query()->create([
            'user_id' => $user->id,
            'organization_id' => $organization->id,
            'company_name' => $name,
            'company_type' => 'municipal_hall',
            'lgu_type' => 'municipality',
            'city_municipality' => $city,
            'company_address' => 'Municipal Hall, '.$city.', Cavite, Philippines',
            'verification_status' => $verificationStatus,
            'industry_type' => 'Local Government',
        ]);

        return compact('user', 'organization', 'company', 'adminRole');
    }

    /**
     * @return array{user:User, organization:Organization, company:Company}
     */
    private function createCompanyHost(string $verificationStatus): array
    {
        foreach (['org.manage_internships', 'org.approve_ojt_logs'] as $key) {
            Permission::query()->updateOrCreate(
                ['key' => $key],
                ['scope' => 'organization', 'description' => $key]
            );
        }

        $user = User::factory()->create(['role' => 'company', 'is_active' => true]);
        $organization = $this->makeOrg('Audit Company Host', 'company', $user);
        $role = $this->makeRole($organization, 'company', ['org.manage_internships', 'org.approve_ojt_logs']);
        $this->join($organization, $user, $role);
        $company = Company::query()->create([
            'user_id' => $user->id,
            'organization_id' => $organization->id,
            'company_name' => 'Audit Company Host',
            'verification_status' => $verificationStatus,
        ]);

        return compact('user', 'organization', 'company');
    }

    /**
     * @return array<string, mixed>
     */
    private function createAcceptedPlacementLifecycle(): array
    {
        foreach ([
            'org.approve_ojt_logs',
            'org.view_ojt_progress',
            'org.manage_internships',
            'org.approve_applications',
        ] as $key) {
            Permission::query()->updateOrCreate(
                ['key' => $key],
                ['scope' => 'organization', 'description' => $key]
            );
        }

        $schoolUser = User::factory()->create(['role' => 'school', 'is_active' => true]);
        $schoolOrg = $this->makeOrg('Audit DTR School', 'school', $schoolUser);
        $schoolRole = $this->makeRole($schoolOrg, 'school', ['org.approve_ojt_logs', 'org.view_ojt_progress']);
        $this->join($schoolOrg, $schoolUser, $schoolRole);
        $school = School::query()->create([
            'user_id' => $schoolUser->id,
            'organization_id' => $schoolOrg->id,
            'institution_name' => 'Audit DTR School',
            'subscription_code' => 'ADT-'.strtoupper(substr(uniqid(), -4)),
            'verification_status' => 'approved',
        ]);

        $host = $this->createMunicipalHall('approved', 'Municipality of Lifecycle — Municipal Hall', 'Tanza');
        $internship = Internship::query()->create([
            'company_id' => $host['company']->id,
            'host_type' => 'company',
            'title' => 'LGU Lifecycle Internship',
            'status' => 'active',
        ]);

        $studentUser = User::factory()->create(['role' => 'student', 'name' => 'LGU Intern', 'is_active' => true]);
        $student = Student::query()->create([
            'user_id' => $studentUser->id,
            'school_id' => $school->id,
            'organization_id' => $schoolOrg->id,
            'school_name' => $school->institution_name,
            'school_subscription_code' => $school->subscription_code,
            'course' => 'BSIT',
            'year_level' => '4th Year',
        ]);

        $application = Application::query()->create([
            'internship_id' => $internship->id,
            'student_id' => $student->id,
            'company_id' => $host['company']->id,
            'status' => 'accepted',
            'internship_title' => $internship->title,
            'student_name' => $studentUser->name,
            'student_email' => $studentUser->email,
        ]);

        return [
            'schoolUser' => $schoolUser,
            'hostUser' => $host['user'],
            'hostCompany' => $host['company'],
            'studentUser' => $studentUser,
            'application' => $application,
        ];
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

    private function addStaff(Organization $organization, Role $role, string $email): User
    {
        $staff = User::factory()->create([
            'role' => 'company',
            'email' => $email,
            'is_active' => true,
        ]);
        $this->join($organization, $staff, $role);

        return $staff;
    }
}
