<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DocumentUploadSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function actingStudent(): User
    {
        $user = User::factory()->create([
            'role' => 'student',
            'email' => 'docs.'.uniqid('', true).'@example.com',
            'is_active' => true,
            'profile_setup_complete' => true,
        ]);

        Sanctum::actingAs($user);

        return $user;
    }

    public function test_upload_without_status_creates_unverified_document(): void
    {
        Storage::fake('public');
        $user = $this->actingStudent();

        $response = $this->post('/api/documents', [
            'category' => 'general',
            'files' => [
                UploadedFile::fake()->create('resume.pdf', 100, 'application/pdf'),
            ],
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.0.status', 'pending');

        $document = Document::query()->where('owner_id', $user->id)->first();
        $this->assertNotNull($document);
        $this->assertNull($document->verified_at);
        $this->assertSame('pending', $response->json('data.0.status'));
    }

    public function test_client_status_approved_cannot_verify_document_on_upload(): void
    {
        Storage::fake('public');
        $user = $this->actingStudent();

        $response = $this->post('/api/documents', [
            'category' => 'general',
            'status' => 'approved',
            'files' => [
                UploadedFile::fake()->create('approved.pdf', 100, 'application/pdf'),
            ],
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.0.status', 'pending');

        $document = Document::query()->where('owner_id', $user->id)->latest('id')->first();
        $this->assertNotNull($document);
        $this->assertNull($document->verified_at);
    }

    public function test_client_status_pending_cannot_verify_document_on_upload(): void
    {
        Storage::fake('public');
        $user = $this->actingStudent();

        $response = $this->post('/api/documents', [
            'category' => 'general',
            'status' => 'pending',
            'files' => [
                UploadedFile::fake()->create('pending.pdf', 100, 'application/pdf'),
            ],
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertCreated();
        $document = Document::query()->where('owner_id', $user->id)->latest('id')->first();
        $this->assertNotNull($document);
        $this->assertNull($document->verified_at);
        $this->assertSame('pending', $response->json('data.0.status'));
    }

    public function test_client_status_rejected_cannot_verify_document_on_upload(): void
    {
        Storage::fake('public');
        $user = $this->actingStudent();

        $response = $this->post('/api/documents', [
            'category' => 'general',
            'status' => 'rejected',
            'files' => [
                UploadedFile::fake()->create('rejected.pdf', 100, 'application/pdf'),
            ],
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertCreated();
        $document = Document::query()->where('owner_id', $user->id)->latest('id')->first();
        $this->assertNotNull($document);
        $this->assertNull($document->verified_at);
        $this->assertSame('pending', $response->json('data.0.status'));
    }
}
