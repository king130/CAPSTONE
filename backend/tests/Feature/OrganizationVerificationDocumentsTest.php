<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\OrganizationVerificationDocument;
use App\Models\OrganizationVerificationDocumentHistory;
use App\Models\Permission;
use App\Models\Role;
use App\Models\School;
use App\Models\Subscription;
use App\Models\User;
use App\Support\OrganizationVerificationRequirements;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrganizationVerificationDocumentsTest extends TestCase
{
    use RefreshDatabase;

    private function pdfFile(string $name = 'doc.pdf', int $kilobytes = 20): UploadedFile
    {
        $content = "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n".str_repeat('A', max(0, $kilobytes * 1024 - 64));

        return UploadedFile::fake()->createWithContent($name, $content);
    }

    private function ensureManagePermission(): void
    {
        Permission::query()->updateOrCreate(
            ['key' => 'org.manage_internships'],
            ['scope' => 'organization', 'description' => 'Manage internships']
        );
    }

    /**
     * @return array{user: User, organization: Organization, company?: Company, school?: School, staff: User}
     */
    private function createOrg(string $type, string $verificationStatus = 'pending'): array
    {
        $this->ensureManagePermission();
        $owner = User::factory()->create([
            'role' => $type === 'municipal_hall' ? 'municipal_hall' : $type,
            'is_active' => true,
        ]);
        $subscription = Subscription::query()->create([
            'plan' => 'free',
            'status' => 'active',
            'billing_cycle' => 'monthly',
        ]);
        $organization = Organization::query()->create([
            'name' => 'Verify '.$type,
            'type' => $type,
            'subscription_id' => $subscription->id,
            'owner_user_id' => $owner->id,
            'is_active' => true,
        ]);
        $role = Role::query()->create([
            'tenant_id' => $organization->id,
            'name' => 'Owner',
            'slug' => 'owner_'.uniqid(),
            'scope' => 'organization',
            'organization_type' => $type === 'municipal_hall' ? 'company' : $type,
        ]);
        $role->permissions()->sync(Permission::query()->where('key', 'org.manage_internships')->pluck('id'));
        OrganizationMembership::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $owner->id,
            'role_id' => $role->id,
            'status' => 'active',
            'title' => 'Owner',
        ]);
        $owner->forceFill(['tenant_id' => $organization->id, 'role_id' => $role->id])->save();

        $school = null;
        $company = null;
        if ($type === 'school') {
            $school = School::query()->create([
                'user_id' => $owner->id,
                'organization_id' => $organization->id,
                'institution_name' => 'Verify School',
                'verification_status' => $verificationStatus,
            ]);
        } else {
            $company = Company::query()->create([
                'user_id' => $owner->id,
                'organization_id' => $organization->id,
                'company_name' => 'Verify '.$type,
                'verification_status' => $verificationStatus,
            ]);
        }

        $staffRole = Role::query()->create([
            'tenant_id' => $organization->id,
            'name' => 'Staff',
            'slug' => 'staff_'.uniqid(),
            'scope' => 'organization',
            'organization_type' => $type === 'municipal_hall' ? 'company' : $type,
        ]);
        $staff = User::factory()->create([
            'role' => $type === 'municipal_hall' ? 'company' : $type,
            'is_active' => true,
            'tenant_id' => $organization->id,
            'role_id' => $staffRole->id,
        ]);
        OrganizationMembership::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $staff->id,
            'role_id' => $staffRole->id,
            'status' => 'active',
            'title' => 'Staff',
        ]);

        return compact('owner', 'organization', 'company', 'school', 'staff') + ['user' => $owner];
    }

    public function test_required_lists_match_config_for_each_org_type(): void
    {
        foreach (['school', 'company', 'municipal_hall'] as $type) {
            $ctx = $this->createOrg($type);
            Sanctum::actingAs($ctx['user']);
            $res = $this->getJson('/api/organization-verification/documents');
            $res->assertOk();
            $expected = OrganizationVerificationRequirements::forOrganizationType($type);
            $this->assertSame(
                array_column($expected, 'type'),
                array_column($res->json('meta.requirements'), 'type'),
                $type
            );
            $this->assertSame(
                array_column($expected, 'required'),
                array_column($res->json('meta.requirements'), 'required'),
                $type
            );
        }
    }

    /**
     * @dataProvider hostTypesProvider
     */
    public function test_upload_validation_private_storage_and_no_auto_verify(string $type): void
    {
        Storage::fake('local');
        $ctx = $this->createOrg($type);
        Sanctum::actingAs($ctx['user']);

        $required = OrganizationVerificationRequirements::requiredTypes($ctx['organization']);
        $firstType = $required[0];

        $ok = $this->post('/api/organization-verification/documents', [
            'document_type' => $firstType,
            'file' => $this->pdfFile('ok.pdf'),
        ], ['Accept' => 'application/json']);
        $ok->assertCreated();
        $path = OrganizationVerificationDocument::query()->first()->file_path;
        Storage::disk('local')->assertExists($path);
        $this->assertStringNotContainsString('public/', $path);

        // Org must NOT auto-verify or even move under_review until submit.
        if ($type === 'school') {
            $this->assertSame('pending', $ctx['school']->fresh()->verification_status);
        } else {
            $this->assertSame('pending', $ctx['company']->fresh()->verification_status);
        }

        // Extension spoof: .pdf with executable-ish content / wrong MIME
        $spoof = UploadedFile::fake()->createWithContent('evil.pdf', "MZ\x90\x00fake-exe");
        $this->post('/api/organization-verification/documents', [
            'document_type' => $firstType,
            'file' => $spoof,
        ], ['Accept' => 'application/json'])->assertStatus(422);

        // Oversized
        $this->post('/api/organization-verification/documents', [
            'document_type' => $firstType,
            'file' => $this->pdfFile('big.pdf', OrganizationVerificationRequirements::maxKilobytes() + 50),
        ], ['Accept' => 'application/json'])->assertStatus(422);
    }

    /**
     * @dataProvider hostTypesProvider
     */
    public function test_authorized_download_cross_org_and_staff_forbidden(string $type): void
    {
        Storage::fake('local');
        $ctx = $this->createOrg($type);
        $other = $this->createOrg($type === 'school' ? 'company' : 'school');
        Sanctum::actingAs($ctx['user']);

        $docType = OrganizationVerificationRequirements::requiredTypes($ctx['organization'])[0];
        $docId = $this->post('/api/organization-verification/documents', [
            'document_type' => $docType,
            'file' => $this->pdfFile(),
        ], ['Accept' => 'application/json'])->json('data.id');

        $this->get("/api/organization-verification/documents/{$docId}/file")->assertOk();

        Sanctum::actingAs($other['user']);
        $this->getJson("/api/organization-verification/documents/{$docId}/file")->assertForbidden();

        Sanctum::actingAs($ctx['staff']);
        $this->post('/api/organization-verification/documents', [
            'document_type' => $docType,
            'file' => $this->pdfFile('staff.pdf'),
        ], ['Accept' => 'application/json'])->assertForbidden();

        $this->deleteJson("/api/organization-verification/documents/{$docId}")->assertForbidden();
    }

    /**
     * @dataProvider hostTypesProvider
     */
    public function test_submit_review_history_and_reupload_after_rejection(string $type): void
    {
        Storage::fake('local');
        $ctx = $this->createOrg($type);
        Sanctum::actingAs($ctx['user']);

        foreach (OrganizationVerificationRequirements::requiredTypes($ctx['organization']) as $docType) {
            $this->post('/api/organization-verification/documents', [
                'document_type' => $docType,
                'file' => $this->pdfFile($docType.'.pdf'),
            ], ['Accept' => 'application/json'])->assertCreated();
        }

        $this->postJson('/api/organization-verification/submit')->assertOk();
        if ($type === 'school') {
            $this->assertSame('under_review', $ctx['school']->fresh()->verification_status);
        } else {
            $this->assertSame('under_review', $ctx['company']->fresh()->verification_status);
        }

        $doc = OrganizationVerificationDocument::query()->first();
        Permission::query()->updateOrCreate(
            ['key' => 'platform.manage_users'],
            ['scope' => 'platform', 'description' => 'Manage platform users']
        );
        $platformRole = Role::query()->create([
            'name' => 'Platform Admin',
            'slug' => 'platform_admin_'.uniqid(),
            'scope' => 'platform',
        ]);
        $platformRole->permissions()->sync([
            Permission::query()->where('key', 'platform.manage_users')->value('id'),
        ]);
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
            'platform_role_id' => $platformRole->id,
        ]);
        Sanctum::actingAs($admin);
        $this->patchJson('/api/admin/verification-documents/'.$doc->id, [
            'status' => 'rejected',
            'reason' => 'Blurry scan',
        ])->assertOk();

        $this->assertSame(0, OrganizationVerificationDocumentHistory::query()->count());

        Sanctum::actingAs($ctx['user']);
        $this->post("/api/organization-verification/documents/{$doc->id}/resubmit", [
            'file' => $this->pdfFile('clear.pdf'),
        ], ['Accept' => 'application/json'])->assertOk();

        $this->assertSame(1, OrganizationVerificationDocumentHistory::query()->count());
        $this->assertSame('pending', $doc->fresh()->status);

        // Approving documents must not auto-verify the organization.
        Sanctum::actingAs($admin);
        foreach (OrganizationVerificationDocument::query()->get() as $row) {
            $this->patchJson('/api/admin/verification-documents/'.$row->id, [
                'status' => 'approved',
            ])->assertOk();
        }
        if ($type === 'school') {
            $this->assertNotSame('approved', $ctx['school']->fresh()->verification_status);
        } else {
            $this->assertNotSame('approved', $ctx['company']->fresh()->verification_status);
        }
    }

    public function test_existing_approved_org_stays_approved_with_missing_docs_notice(): void
    {
        Storage::fake('local');
        $ctx = $this->createOrg('company', 'approved');
        Sanctum::actingAs($ctx['user']);

        $res = $this->getJson('/api/organization-verification/documents');
        $res->assertOk();
        $this->assertSame('approved', $res->json('meta.verificationStatus'));
        $this->assertNotEmpty($res->json('meta.documentsMissingNotice'));
        $this->assertSame('approved', $ctx['company']->fresh()->verification_status);

        $docType = OrganizationVerificationRequirements::requiredTypes($ctx['organization'])[0];
        $this->post('/api/organization-verification/documents', [
            'document_type' => $docType,
            'file' => $this->pdfFile(),
        ], ['Accept' => 'application/json'])->assertCreated();
        $this->assertSame('approved', $ctx['company']->fresh()->verification_status);
    }

    public static function hostTypesProvider(): array
    {
        return [
            'school' => ['school'],
            'company' => ['company'],
            'municipal_hall' => ['municipal_hall'],
        ];
    }
}
