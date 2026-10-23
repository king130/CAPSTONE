<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Document;
use App\Models\Internship;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Permission;
use App\Models\Role;
use App\Models\School;
use App\Models\Student;
use App\Models\Subscription;
use App\Models\User;
use App\Support\InternshipDocumentRequirements;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InternshipEndorsementRequirementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{schoolUser: User, school: School, companyUser: User, company: Company, mhUser: User, mhCompany: Company, studentUser: User, student: Student}
     */
    private function createContext(): array
    {
        Permission::query()->updateOrCreate(
            ['key' => 'org.manage_internships'],
            ['scope' => 'organization', 'description' => 'Create and update internships']
        );

        $schoolUser = User::factory()->create(['role' => 'school', 'is_active' => true]);
        $schoolSub = Subscription::query()->create(['plan' => 'standard', 'status' => 'active', 'billing_cycle' => 'monthly']);
        $schoolOrg = Organization::query()->create([
            'name' => 'Endorse School',
            'type' => 'school',
            'subscription_id' => $schoolSub->id,
            'owner_user_id' => $schoolUser->id,
            'settings' => [],
            'is_active' => true,
        ]);
        $school = School::query()->create([
            'user_id' => $schoolUser->id,
            'organization_id' => $schoolOrg->id,
            'institution_name' => 'Endorse School',
            'subscription_code' => 'END-'.strtoupper(substr(uniqid(), -4)),
            'verification_status' => 'approved',
        ]);

        $makeHost = function (string $type, string $name) {
            $user = User::factory()->create([
                'role' => $type === 'municipal_hall' ? 'municipal_hall' : 'company',
                'is_active' => true,
            ]);
            $sub = Subscription::query()->create(['plan' => 'standard', 'status' => 'active', 'billing_cycle' => 'monthly']);
            $org = Organization::query()->create([
                'name' => $name,
                'type' => $type,
                'subscription_id' => $sub->id,
                'owner_user_id' => $user->id,
                'settings' => [],
                'is_active' => true,
            ]);
            $role = Role::query()->create([
                'tenant_id' => $org->id,
                'name' => 'Host Admin',
                'slug' => 'host_admin_'.uniqid(),
                'scope' => 'organization',
                'organization_type' => $type === 'municipal_hall' ? 'company' : 'company',
            ]);
            $role->permissions()->sync(Permission::query()->where('key', 'org.manage_internships')->pluck('id'));
            OrganizationMembership::query()->create([
                'organization_id' => $org->id,
                'user_id' => $user->id,
                'role_id' => $role->id,
                'status' => 'active',
                'title' => 'Admin',
            ]);
            $user->forceFill(['tenant_id' => $org->id, 'role_id' => $role->id])->save();
            $company = Company::query()->create([
                'user_id' => $user->id,
                'organization_id' => $org->id,
                'company_name' => $name,
                'verification_status' => 'approved',
            ]);

            return compact('user', 'company', 'org');
        };

        $companyHost = $makeHost('company', 'Endorse Company');
        $mhHost = $makeHost('municipal_hall', 'Endorse Municipal Hall');

        foreach ([$companyHost, $mhHost] as $host) {
            Contract::query()->create([
                'school_user_id' => $schoolUser->id,
                'school_name' => $school->institution_name,
                'company_user_id' => $host['user']->id,
                'company_name' => $host['company']->company_name,
                'requested_by_role' => 'school',
                'requested_by_user_id' => $schoolUser->id,
                'partner_user_id' => $host['user']->id,
                'status' => 'active',
                'subject' => 'Endorse MOA',
            ]);
        }

        $studentUser = User::factory()->create(['role' => 'student', 'name' => 'Endorse Intern', 'is_active' => true]);
        $student = Student::query()->create([
            'user_id' => $studentUser->id,
            'school_id' => $school->id,
            'school_name' => $school->institution_name,
            'course' => 'BSIT',
            'year_level' => '4th Year',
        ]);

        \App\Models\SchoolCourseRequirement::query()->create([
            'school_id' => $school->id,
            'course_name' => 'BSIT',
            'course_name_key' => \App\Models\SchoolCourseRequirement::courseNameKey('BSIT'),
            'required_hours' => 486,
            'eligible_year_levels' => \App\Support\StudentYearLevel::DEFAULT_ELIGIBLE,
        ]);

        return [
            'schoolUser' => $schoolUser,
            'school' => $school,
            'companyUser' => $companyHost['user'],
            'company' => $companyHost['company'],
            'mhUser' => $mhHost['user'],
            'mhCompany' => $mhHost['company'],
            'studentUser' => $studentUser,
            'student' => $student,
        ];
    }

    private function pdfFile(string $name = 'endorsement.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n");
    }

    private function storeEndorsement(User $studentUser): Document
    {
        Storage::fake('local');
        Sanctum::actingAs($studentUser);
        $id = $this->post('/api/documents', [
            'category' => InternshipDocumentRequirements::ENDORSEMENT_CATEGORY,
            'files' => [$this->pdfFile()],
        ], ['Accept' => 'application/json'])->json('data.0.id');

        return Document::query()->findOrFail($id);
    }

    /**
     * @dataProvider hostTypesProvider
     */
    public function test_host_create_and_edit_accept_only_endorsement(string $type): void
    {
        $ctx = $this->createContext();
        $user = $type === 'municipal_hall' ? $ctx['mhUser'] : $ctx['companyUser'];
        Sanctum::actingAs($user);

        $ok = $this->postJson('/api/internships', [
            'title' => 'Host Role '.$type,
            'status' => 'active',
            'required_documents' => ['Endorsement'],
            'requires_resume' => true,
        ]);
        $ok->assertCreated();
        $ok->assertJsonPath('data.requiredDocuments.0', 'Endorsement');
        $ok->assertJsonPath('data.requiresResume', false);
        $this->assertFalse((bool) Internship::query()->find($ok->json('data.id'))?->requires_resume);

        $rejected = $this->postJson('/api/internships', [
            'title' => 'Bad Docs '.$type,
            'status' => 'active',
            'required_documents' => ['Resume', 'MOA', 'Endorsement'],
        ]);
        $rejected->assertStatus(422);

        $internshipId = $ok->json('data.id');
        $this->patchJson('/api/internships/'.$internshipId, [
            'required_documents' => ['NBI Clearance'],
        ])->assertStatus(422);

        $this->patchJson('/api/internships/'.$internshipId, [
            'required_documents' => ['Endorsement'],
            'requires_resume' => true,
        ])->assertOk()->assertJsonPath('data.requiresResume', false);
    }

    /**
     * @dataProvider hostTypesProvider
     */
    public function test_apply_requires_endorsement_not_resume(string $type): void
    {
        Storage::fake('local');
        $ctx = $this->createContext();
        $company = $type === 'municipal_hall' ? $ctx['mhCompany'] : $ctx['company'];
        $internship = Internship::query()->create([
            'company_id' => $company->id,
            'company_name' => $company->company_name,
            'host_type' => 'company',
            'host_name' => $company->company_name,
            'title' => 'Apply '.$type,
            'status' => 'active',
            'approval_status' => 'approved',
            'slots_available' => 2,
            'required_documents' => ['Resume', 'MOA'], // legacy junk ignored
            'requires_resume' => true, // legacy flag ignored for apply
        ]);

        Sanctum::actingAs($ctx['studentUser']);
        $this->postJson('/api/applications', [
            'internship_id' => $internship->id,
        ])->assertStatus(422)->assertJsonPath('code', 'endorsement_required');

        $doc = $this->storeEndorsement($ctx['studentUser']);
        Sanctum::actingAs($ctx['studentUser']);
        $this->postJson('/api/applications', [
            'internship_id' => $internship->id,
            'endorsement_url' => $doc->file_url,
        ])->assertCreated();

        $app = Application::query()->first();
        $this->assertNotNull($app);
        $this->assertContains($doc->file_url, $app->documents ?? []);
    }

    public function test_reuse_profile_endorsement_and_cross_org_download_forbidden(): void
    {
        Storage::fake('local');
        $ctx = $this->createContext();
        $doc = $this->storeEndorsement($ctx['studentUser']);

        Sanctum::actingAs($ctx['studentUser']);
        $this->get('/api/documents/'.$doc->id.'/file')->assertOk();

        $other = User::factory()->create(['role' => 'student', 'is_active' => true]);
        Sanctum::actingAs($other);
        $this->getJson('/api/documents/'.$doc->id.'/file')->assertForbidden();

        Sanctum::actingAs($ctx['studentUser']);
        $internship = Internship::query()->create([
            'company_id' => $ctx['company']->id,
            'company_name' => $ctx['company']->company_name,
            'host_type' => 'company',
            'host_name' => $ctx['company']->company_name,
            'title' => 'Reuse Endorsement',
            'status' => 'active',
            'approval_status' => 'approved',
            'slots_available' => 1,
            'required_documents' => [InternshipDocumentRequirements::ENDORSEMENT_LABEL],
            'requires_resume' => false,
        ]);

        // Reuse without passing endorsement_url — owned endorsement is auto-picked.
        $this->postJson('/api/applications', [
            'internship_id' => $internship->id,
        ])->assertCreated();
    }

    public function test_existing_posting_with_old_requirements_still_lists_endorsement_only(): void
    {
        $ctx = $this->createContext();
        $internship = Internship::query()->create([
            'company_id' => $ctx['company']->id,
            'company_name' => $ctx['company']->company_name,
            'host_type' => 'company',
            'host_name' => $ctx['company']->company_name,
            'title' => 'Legacy Docs',
            'status' => 'active',
            'approval_status' => 'approved',
            'slots_available' => 1,
            'required_documents' => ['Resume', 'Transcript', 'Endorsement'],
            'requires_resume' => true,
        ]);

        Sanctum::actingAs($ctx['studentUser']);
        $res = $this->getJson('/api/internships/'.$internship->id);
        $res->assertOk();
        $res->assertJsonPath('data.requiredDocuments', ['Endorsement']);
        $res->assertJsonPath('data.requiresResume', false);
        $this->assertSame(['Resume', 'Transcript', 'Endorsement'], $internship->fresh()->required_documents);
    }

    public function test_mime_spoof_endorsement_rejected(): void
    {
        Storage::fake('local');
        $ctx = $this->createContext();
        Sanctum::actingAs($ctx['studentUser']);

        $this->post('/api/documents', [
            'category' => InternshipDocumentRequirements::ENDORSEMENT_CATEGORY,
            'files' => [UploadedFile::fake()->createWithContent('evil.pdf', "MZ\x00fake")],
        ], ['Accept' => 'application/json'])->assertStatus(422);
    }

    public static function hostTypesProvider(): array
    {
        return [
            'company' => ['company'],
            'municipal_hall' => ['municipal_hall'],
        ];
    }
}
