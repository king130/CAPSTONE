<?php

namespace Tests\Feature;

use App\Mail\OtpMail;
use App\Models\EmailOtp;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Permission;
use App\Models\Role;
use App\Models\School;
use App\Models\Subscription;
use App\Models\User;
use App\Services\OrganizationAccountProvisioner;
use App\Support\OrganizationVerificationRequirements;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmailOtpVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function pdfFile(string $name = 'doc.pdf'): UploadedFile
    {
        $content = "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n".str_repeat('A', 2048);

        return UploadedFile::fake()->createWithContent($name, $content);
    }

    private function registerSchool(string $email): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/auth/register', [
            'email' => $email,
            'password' => 'School@2026',
            'fullName' => 'School Owner',
            'role' => 'school',
            'profile' => [
                'institutionName' => 'OTP Cavite Academy',
                'schoolAddress' => 'Indang Campus, Indang, Cavite',
                'locationArea' => 'Indang',
                'barangay' => 'Poblacion',
            ],
        ]);
    }

    private function captureLatestOtpCode(string $email): string
    {
        $code = null;
        Mail::assertSent(OtpMail::class, function (OtpMail $mail) use ($email, &$code) {
            if (! $mail->hasTo($email)) {
                return false;
            }
            $code = $mail->code;

            return true;
        });

        $this->assertNotNull($code);

        return (string) $code;
    }

    public function test_self_registration_requires_otp_before_document_upload_but_allows_login(): void
    {
        Mail::fake();
        Storage::fake('local');

        $email = 'otp.school.'.uniqid('', true).'@example.com';
        $register = $this->registerSchool($email);
        $register->assertCreated();
        $register->assertJsonPath('user.emailVerificationRequired', true);
        $register->assertJsonPath('emailOtp.required', true);
        $register->assertJsonPath('emailOtp.sent', true);
        $this->assertNull(User::query()->where('email', $email)->value('email_verified_at'));

        $login = $this->postJson('/api/auth/login', [
            'email' => $email,
            'password' => 'School@2026',
        ]);
        $login->assertOk();
        $login->assertJsonPath('user.emailVerificationRequired', true);

        $user = User::query()->where('email', $email)->firstOrFail();
        Sanctum::actingAs($user);

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('emailVerificationRequired', true);

        $this->getJson('/api/organization-verification/documents')->assertOk();

        $documentType = OrganizationVerificationRequirements::forOrganizationType('school')[0]['type'];
        $upload = $this->post('/api/organization-verification/documents', [
            'document_type' => $documentType,
            'file' => $this->pdfFile(),
        ], ['Accept' => 'application/json']);
        $upload->assertForbidden();
        $upload->assertJsonPath('code', 'email_verification_required');

        $code = $this->captureLatestOtpCode($email);
        $this->postJson('/api/auth/email-otp/verify', ['code' => $code])
            ->assertOk()
            ->assertJsonPath('user.emailVerificationRequired', false);

        $this->assertNotNull($user->fresh()->email_verified_at);

        $uploadAfter = $this->post('/api/organization-verification/documents', [
            'document_type' => $documentType,
            'file' => $this->pdfFile('after.pdf'),
        ], ['Accept' => 'application/json']);
        $uploadAfter->assertCreated();
    }

    public function test_admin_provisioned_org_owner_skips_otp_gate(): void
    {
        Mail::fake();
        Storage::fake('local');

        $created = app(OrganizationAccountProvisioner::class)->create([
            'fullName' => 'Admin Created Owner',
            'email' => 'admin.created.'.uniqid('', true).'@example.com',
            'temporaryPassword' => 'TempPass1!',
            'role' => 'school',
            'profile' => [
                'institutionName' => 'Admin Provisioned School',
                'schoolAddress' => 'Indang Campus, Indang, Cavite',
                'locationArea' => 'Indang',
                'barangay' => 'Poblacion',
            ],
            'subscriptionPlan' => 'standard',
            'billingCycle' => 'monthly',
        ]);

        /** @var User $user */
        $user = $created['user'];
        $this->assertNotNull($user->email_verified_at);

        Sanctum::actingAs($user->fresh());
        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('emailVerificationRequired', false);

        $upload = $this->post('/api/organization-verification/documents', [
            'document_type' => OrganizationVerificationRequirements::forOrganizationType('school')[0]['type'],
            'file' => $this->pdfFile(),
        ], ['Accept' => 'application/json']);
        $upload->assertCreated();
        Mail::assertNothingSent();
    }

    public function test_verified_existing_owner_can_upload_without_otp(): void
    {
        Storage::fake('local');
        Permission::query()->updateOrCreate(
            ['key' => 'org.manage_internships'],
            ['scope' => 'organization', 'description' => 'Manage internships']
        );

        $user = User::factory()->create([
            'role' => 'school',
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
        $subscription = Subscription::query()->create([
            'plan' => 'free',
            'status' => 'active',
            'billing_cycle' => 'monthly',
        ]);
        $organization = Organization::query()->create([
            'name' => 'Grandfather School',
            'type' => 'school',
            'subscription_id' => $subscription->id,
            'owner_user_id' => $user->id,
            'is_active' => true,
        ]);
        $role = Role::query()->create([
            'tenant_id' => $organization->id,
            'name' => 'Owner',
            'slug' => 'owner_'.uniqid(),
            'scope' => 'organization',
            'organization_type' => 'school',
        ]);
        OrganizationMembership::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);
        $user->forceFill(['tenant_id' => $organization->id, 'role_id' => $role->id])->save();
        School::query()->create([
            'user_id' => $user->id,
            'organization_id' => $organization->id,
            'institution_name' => 'Grandfather School',
            'verification_status' => 'pending',
        ]);

        Sanctum::actingAs($user);
        $this->post('/api/organization-verification/documents', [
            'document_type' => OrganizationVerificationRequirements::forOrganizationType('school')[0]['type'],
            'file' => $this->pdfFile(),
        ], ['Accept' => 'application/json'])->assertCreated();
    }

    public function test_invalid_code_and_max_attempts_then_resend(): void
    {
        Mail::fake();
        $email = 'otp.attempts.'.uniqid('', true).'@example.com';
        $this->registerSchool($email)->assertCreated();
        $user = User::query()->where('email', $email)->firstOrFail();
        Sanctum::actingAs($user);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/email-otp/verify', ['code' => '000000'])
                ->assertStatus(422);
        }

        $otp = EmailOtp::query()->where('user_id', $user->id)->latest('id')->first();
        $this->assertNotNull($otp?->consumed_at);

        $this->postJson('/api/auth/email-otp/verify', ['code' => '000000'])
            ->assertStatus(422);

        // Cooldown blocks immediate resend from registration OTP create time — travel forward.
        $this->travel(61)->seconds();
        Mail::fake();
        $this->postJson('/api/auth/email-otp/resend')->assertOk();
        $code = $this->captureLatestOtpCode($email);
        $this->assertTrue(Hash::check($code, EmailOtp::query()->where('user_id', $user->id)->whereNull('consumed_at')->latest('id')->value('code_hash')));

        $this->postJson('/api/auth/email-otp/verify', ['code' => $code])
            ->assertOk()
            ->assertJsonPath('user.emailVerificationRequired', false);
    }

    public function test_expired_code_is_rejected(): void
    {
        Mail::fake();
        $email = 'otp.expired.'.uniqid('', true).'@example.com';
        $this->registerSchool($email)->assertCreated();
        $user = User::query()->where('email', $email)->firstOrFail();
        $code = $this->captureLatestOtpCode($email);

        Sanctum::actingAs($user);
        $this->travel(11)->minutes();

        $this->postJson('/api/auth/email-otp/verify', ['code' => $code])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['code']);

        $this->assertNull($user->fresh()->email_verified_at);
        $this->assertNotNull(
            EmailOtp::query()->where('user_id', $user->id)->latest('id')->value('consumed_at')
        );
    }
}
