<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Organization;
use App\Models\OrganizationMedia;
use App\Models\OrganizationMembership;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\SubscriptionPlanDefinition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrganizationMediaSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function fakeJpeg(string $name = 'photo.jpg', int $kilobytes = 10): UploadedFile
    {
        // Minimal valid JPEG binary (no GD extension required).
        $bytes = base64_decode(
            '/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAn/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAGfAP/EABQQAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQEAAQUCf//EABQRAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQMBAT8Bf//EABQRAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQIBAT8Bf//Z'
        );
        $padding = max(0, ($kilobytes * 1024) - strlen($bytes));
        if ($padding > 0) {
            $bytes .= str_repeat("\0", $padding);
        }

        return UploadedFile::fake()->createWithContent($name, $bytes);
    }

    /**
     * @return array{user: User, organization: Organization}
     */
    private function createCompanyContext(string $plan = 'free'): array
    {
        Permission::query()->updateOrCreate(
            ['key' => 'org.manage_profile'],
            ['scope' => 'organization', 'description' => 'org.manage_profile']
        );

        SubscriptionPlanDefinition::query()->updateOrCreate(
            ['slug' => 'free'],
            [
                'name' => 'Free',
                'description' => 'Free',
                'school_price' => 0,
                'company_price' => 0,
                'school_features' => [],
                'company_features' => [],
                'school_coordinators_limit' => 1,
                'school_students_limit' => 5,
                'company_accounts_limit' => 1,
                'company_internships_limit' => 3,
                'organization_photos_limit' => 3,
                'is_active' => true,
            ]
        );
        SubscriptionPlanDefinition::query()->updateOrCreate(
            ['slug' => 'standard'],
            [
                'name' => 'Standard',
                'description' => 'Standard',
                'school_price' => 1999,
                'company_price' => 2499,
                'school_features' => [],
                'company_features' => [],
                'school_coordinators_limit' => 5,
                'school_students_limit' => 100,
                'company_accounts_limit' => 5,
                'company_internships_limit' => 999,
                'organization_photos_limit' => 10,
                'is_active' => true,
            ]
        );

        $user = User::factory()->create([
            'role' => 'company',
            'email' => 'media.'.uniqid('', true).'@example.com',
            'is_active' => true,
        ]);
        $sub = Subscription::query()->create([
            'plan' => $plan,
            'status' => 'active',
            'billing_cycle' => 'monthly',
        ]);
        $organization = Organization::query()->create([
            'name' => 'Media Co',
            'type' => 'company',
            'subscription_id' => $sub->id,
            'owner_user_id' => $user->id,
            'settings' => [],
            'is_active' => true,
        ]);
        $role = Role::query()->create([
            'tenant_id' => $organization->id,
            'name' => 'Company Admin',
            'slug' => 'company_admin_'.uniqid(),
            'scope' => 'organization',
            'organization_type' => 'company',
        ]);
        $role->permissions()->sync(
            Permission::query()->where('key', 'org.manage_profile')->pluck('id')
        );
        OrganizationMembership::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
            'status' => 'active',
            'title' => 'Admin',
        ]);
        $user->forceFill([
            'tenant_id' => $organization->id,
            'role_id' => $role->id,
        ])->save();
        Company::query()->create([
            'user_id' => $user->id,
            'organization_id' => $organization->id,
            'company_name' => 'Media Co',
            'verification_status' => 'approved',
        ]);

        return compact('user', 'organization');
    }

    public function test_upload_rejects_svg_non_images_oversize_and_spoofed_extensions(): void
    {
        Storage::fake('public');
        $ctx = $this->createCompanyContext();
        Sanctum::actingAs($ctx['user']);

        $this->post('/api/organization-media', [
            'file' => UploadedFile::fake()->create('logo.svg', 100, 'image/svg+xml'),
        ], ['Accept' => 'application/json'])->assertStatus(422);

        $this->post('/api/organization-media', [
            'file' => UploadedFile::fake()->create('notes.txt', 20, 'text/plain'),
        ], ['Accept' => 'application/json'])->assertStatus(422);

        $this->post('/api/organization-media', [
            'file' => UploadedFile::fake()->create('huge.jpg', 4096, 'image/jpeg'),
        ], ['Accept' => 'application/json'])->assertStatus(422);

        $this->post('/api/organization-media', [
            'file' => UploadedFile::fake()->create('photo.jpg', 40, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertStatus(422);

        $this->assertSame(0, OrganizationMedia::query()->count());
    }

    public function test_cross_organization_delete_forbidden_but_admin_can_delete(): void
    {
        Storage::fake('public');
        $owner = $this->createCompanyContext();
        $intruder = $this->createCompanyContext('standard');

        Sanctum::actingAs($owner['user']);
        $upload = $this->post('/api/organization-media', [
            'file' => $this->fakeJpeg('cover.jpg'),
            'is_cover' => true,
        ], ['Accept' => 'application/json']);
        $upload->assertCreated();
        $mediaId = (int) $upload->json('data.id');

        Sanctum::actingAs($intruder['user']);
        $this->deleteJson('/api/organization-media/'.$mediaId)->assertForbidden();
        $this->assertDatabaseHas('organization_media', ['id' => $mediaId]);

        $admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin.'.uniqid('', true).'@example.com',
            'is_active' => true,
        ]);
        Sanctum::actingAs($admin);
        $this->deleteJson('/api/organization-media/'.$mediaId)->assertNoContent();
        $this->assertDatabaseMissing('organization_media', ['id' => $mediaId]);
    }

    public function test_photo_limit_enforced_per_plan(): void
    {
        Storage::fake('public');
        $ctx = $this->createCompanyContext('free');
        Sanctum::actingAs($ctx['user']);

        for ($i = 0; $i < 3; $i++) {
            $this->post('/api/organization-media', [
                'file' => $this->fakeJpeg("photo{$i}.jpg"),
            ], ['Accept' => 'application/json'])->assertCreated();
        }

        $this->post('/api/organization-media', [
            'file' => $this->fakeJpeg('blocked.jpg'),
        ], ['Accept' => 'application/json'])->assertStatus(422);

        $this->assertSame(3, OrganizationMedia::query()->where('organization_id', $ctx['organization']->id)->count());
    }

    public function test_public_media_index_lists_organization_photos(): void
    {
        Storage::fake('public');
        $ctx = $this->createCompanyContext();
        Sanctum::actingAs($ctx['user']);
        $this->post('/api/organization-media', [
            'file' => $this->fakeJpeg('gallery.jpg'),
            'caption' => 'Lobby',
            'is_cover' => true,
        ], ['Accept' => 'application/json'])->assertCreated();

        $response = $this->getJson('/api/organizations/'.$ctx['organization']->id.'/media');
        $response->assertOk();
        $response->assertJsonPath('data.0.caption', 'Lobby');
        $this->assertNotEmpty($response->json('data.0.url'));
    }
}
