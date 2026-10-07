<?php

namespace Tests\Feature;

use App\Models\AccountSetupToken;
use App\Models\Organization;
use App\Models\School;
use App\Models\Student;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StudentSchoolAttachmentSecurityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{owner: User, organization: Organization, school: School}
     */
    private function createSchool(string $institutionName, string $subscriptionCode): array
    {
        $owner = User::factory()->create([
            'role' => 'school',
            'email' => 'school.'.uniqid('', true).'@example.com',
            'is_active' => true,
        ]);

        $subscription = Subscription::query()->create([
            'plan' => 'standard',
            'status' => 'active',
            'billing_cycle' => 'monthly',
        ]);

        $organization = Organization::query()->create([
            'name' => $institutionName,
            'type' => 'school',
            'subscription_id' => $subscription->id,
            'owner_user_id' => $owner->id,
            'settings' => [],
            'is_active' => true,
        ]);

        $school = School::query()->create([
            'user_id' => $owner->id,
            'organization_id' => $organization->id,
            'institution_name' => $institutionName,
            'subscription_code' => $subscriptionCode,
            'verification_status' => 'approved',
        ]);

        $owner->forceFill(['tenant_id' => $organization->id])->save();

        return compact('owner', 'organization', 'school');
    }

    /**
     * @return array{user: User, student: Student}
     */
    private function createStudent(?School $school = null): array
    {
        $user = User::factory()->create([
            'role' => 'student',
            'email' => 'student.'.uniqid('', true).'@example.com',
            'profile_setup_complete' => true,
        ]);

        $student = Student::query()->create([
            'user_id' => $user->id,
            'school_id' => $school?->id,
            'organization_id' => $school?->organization_id,
            'school_name' => $school?->institution_name,
            'school_subscription_code' => $school?->subscription_code,
            'course' => 'BSIT',
            'year_level' => '3rd Year',
        ]);

        return compact('user', 'student');
    }

    public function test_student_cannot_attach_school_via_profile_school_id(): void
    {
        $target = $this->createSchool('Target University', 'TGT-0001');
        $context = $this->createStudent();

        Sanctum::actingAs($context['user']);

        $response = $this->patchJson('/api/profile', [
            'profile' => [
                'schoolId' => (string) $target['school']->id,
                'course' => 'BSCS',
            ],
        ]);

        $response->assertOk();
        $context['student']->refresh();
        $this->assertNull($context['student']->school_id);
        $this->assertNull($context['student']->organization_id);
        $this->assertSame('BSCS', $context['student']->course);
    }

    public function test_student_cannot_switch_school_via_profile_school_id(): void
    {
        $schoolA = $this->createSchool('School Alpha', 'ALP-0001');
        $schoolB = $this->createSchool('School Beta', 'BET-0001');
        $context = $this->createStudent($schoolA['school']);

        Sanctum::actingAs($context['user']);

        $response = $this->patchJson('/api/profile', [
            'profile' => [
                'schoolId' => (string) $schoolB['school']->id,
            ],
        ]);

        $response->assertOk();
        $context['student']->refresh();
        $this->assertSame($schoolA['school']->id, $context['student']->school_id);
        $this->assertSame($schoolA['organization']->id, $context['student']->organization_id);
        $this->assertNotSame($schoolB['school']->id, $context['student']->school_id);
    }

    public function test_student_cannot_attach_via_profile_organization_id(): void
    {
        $target = $this->createSchool('Org Bind University', 'ORG-0001');
        $context = $this->createStudent();

        Sanctum::actingAs($context['user']);

        $response = $this->patchJson('/api/profile', [
            'profile' => [
                'organizationId' => (string) $target['organization']->id,
            ],
        ]);

        $response->assertOk();
        $context['student']->refresh();
        $this->assertNull($context['student']->school_id);
        $this->assertNull($context['student']->organization_id);
    }

    public function test_student_cannot_attach_via_profile_school_subscription_code(): void
    {
        $target = $this->createSchool('Code Bind University', 'CODE-9999');
        $context = $this->createStudent();

        Sanctum::actingAs($context['user']);

        $response = $this->patchJson('/api/profile', [
            'profile' => [
                'schoolSubscriptionCode' => 'CODE-9999',
            ],
        ]);

        $response->assertOk();
        $context['student']->refresh();
        $this->assertNull($context['student']->school_id);
        $this->assertNull($context['student']->organization_id);
        $this->assertNull($context['student']->school_subscription_code);
    }

    public function test_student_cannot_attach_via_profile_school_name(): void
    {
        $target = $this->createSchool('Exact Match University', 'EXA-0001');
        $context = $this->createStudent();

        Sanctum::actingAs($context['user']);

        $response = $this->patchJson('/api/profile', [
            'profile' => [
                'schoolName' => 'Exact Match University',
            ],
        ]);

        $response->assertOk();
        $context['student']->refresh();
        $this->assertNull($context['student']->school_id);
        $this->assertNull($context['student']->organization_id);
        $this->assertSame('Exact Match University', $context['student']->school_name);
        $this->assertSame($target['school']->id, School::query()->where('institution_name', 'Exact Match University')->value('id'));
    }

    public function test_register_student_role_is_rejected_without_creating_accounts(): void
    {
        $target = $this->createSchool('Register Target University', 'REG-0001');
        $email = 'register.noschool.'.uniqid('', true).'@example.com';
        $userCountBefore = User::query()->count();
        $studentCountBefore = Student::query()->count();

        $response = $this->postJson('/api/auth/register', [
            'email' => $email,
            'password' => 'Password1',
            'fullName' => 'Register Student',
            'role' => 'student',
            'profile' => [
                'schoolId' => (string) $target['school']->id,
                'schoolName' => 'Free Text School',
                'course' => 'BSIT',
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment([
            'message' => 'Student accounts are created by your school. Use the school-issued setup link or login credentials instead of public registration.',
        ]);
        $this->assertSame($userCountBefore, User::query()->count());
        $this->assertSame($studentCountBefore, Student::query()->count());
        $this->assertDatabaseMissing('users', ['email' => $email]);
        $this->assertDatabaseMissing('students', ['school_name' => 'Free Text School']);
    }

    public function test_register_student_with_subscription_code_is_also_rejected(): void
    {
        $this->createSchool('Code Join University', 'JOIN-4242');
        $email = 'register.code.'.uniqid('', true).'@example.com';
        $userCountBefore = User::query()->count();
        $studentCountBefore = Student::query()->count();

        $response = $this->postJson('/api/auth/register', [
            'email' => $email,
            'password' => 'Password1',
            'fullName' => 'Code Join Student',
            'role' => 'student',
            'schoolSubscriptionCode' => 'JOIN-4242',
            'profile' => [
                'course' => 'BSIT',
                'yearLevel' => '4th Year',
            ],
        ]);

        $response->assertStatus(422);
        $this->assertSame($userCountBefore, User::query()->count());
        $this->assertSame($studentCountBefore, Student::query()->count());
        $this->assertDatabaseMissing('users', ['email' => $email]);
    }

    public function test_invited_student_remains_bound_to_school_through_account_setup(): void
    {
        $schoolContext = $this->createSchool('Invite University', 'INV-0001');

        $user = User::factory()->create([
            'role' => 'student',
            'email' => 'invited.'.uniqid('', true).'@example.com',
            'password' => Hash::make('TempPass1'),
            'is_temporary' => true,
            'must_change_password' => true,
            'profile_setup_complete' => false,
        ]);

        $student = Student::query()->create([
            'user_id' => $user->id,
            'school_id' => $schoolContext['school']->id,
            'organization_id' => $schoolContext['organization']->id,
            'school_name' => $schoolContext['school']->institution_name,
            'school_subscription_code' => $schoolContext['school']->subscription_code,
        ]);

        $plainToken = 'setup-token-'.uniqid('', true);
        AccountSetupToken::query()->create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addDay(),
            'invited_by_user_id' => $schoolContext['owner']->id,
        ]);

        $response = $this->postJson('/api/auth/account-setup/complete', [
            'token' => $plainToken,
            'password' => 'NewPassword1',
            'password_confirmation' => 'NewPassword1',
        ]);

        $response->assertOk();
        $student->refresh();
        $user->refresh();
        $this->assertSame($schoolContext['school']->id, $student->school_id);
        $this->assertSame($schoolContext['organization']->id, $student->organization_id);
        $this->assertFalse((bool) $user->is_temporary);
        $this->assertTrue((bool) $user->profile_setup_complete);
    }
}
