<?php

namespace Tests\Feature;

use App\Models\AccountSetupToken;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Permission;
use App\Models\Role;
use App\Models\School;
use App\Models\Student;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StudentOnboardingHardeningTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{user: User, organization: Organization, school: School}
     */
    private function createApprovedSchool(): array
    {
        foreach ([
            'manage_users' => 'Manage users',
            'org.manage_members' => 'Manage organization members',
        ] as $key => $description) {
            Permission::query()->updateOrCreate(
                ['key' => $key],
                ['scope' => 'organization', 'description' => $description]
            );
        }

        $user = User::factory()->create([
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
            'name' => 'Onboarding School Org',
            'type' => 'school',
            'subscription_id' => $subscription->id,
            'owner_user_id' => $user->id,
            'settings' => [],
            'is_active' => true,
        ]);

        $role = Role::query()->create([
            'tenant_id' => $organization->id,
            'name' => 'School Admin',
            'slug' => 'school_admin_'.uniqid(),
            'scope' => 'organization',
            'organization_type' => 'school',
        ]);
        $role->permissions()->sync(
            Permission::query()->whereIn('key', ['manage_users', 'org.manage_members'])->pluck('id')
        );

        OrganizationMembership::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
            'status' => 'active',
            'title' => 'Primary Admin',
        ]);

        $user->forceFill([
            'tenant_id' => $organization->id,
            'role_id' => $role->id,
        ])->save();

        $school = School::query()->create([
            'user_id' => $user->id,
            'organization_id' => $organization->id,
            'institution_name' => 'Onboarding School',
            'subscription_code' => 'ONB-'.strtoupper(substr(uniqid(), -4)),
            'verification_status' => 'approved',
        ]);

        return compact('user', 'organization', 'school');
    }

    public function test_public_student_registration_is_rejected(): void
    {
        $email = 'public.student.'.uniqid('', true).'@example.com';
        $userCountBefore = User::query()->count();
        $studentCountBefore = Student::query()->count();
        $membershipCountBefore = OrganizationMembership::query()->count();
        $tokenCountBefore = AccountSetupToken::query()->count();

        $response = $this->postJson('/api/auth/register', [
            'email' => $email,
            'password' => 'Password1',
            'fullName' => 'Public Student',
            'role' => 'student',
            'profile' => [
                'course' => 'BSIT',
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath(
            'message',
            'Student accounts are created by your school. Use the school-issued setup link or login credentials instead of public registration.'
        );
        $this->assertSame($userCountBefore, User::query()->count());
        $this->assertSame($studentCountBefore, Student::query()->count());
        $this->assertSame($membershipCountBefore, OrganizationMembership::query()->count());
        $this->assertSame($tokenCountBefore, AccountSetupToken::query()->count());
        $this->assertDatabaseMissing('users', ['email' => $email]);
    }

    public function test_guest_student_promotion_is_rejected(): void
    {
        $guest = User::factory()->create([
            'role' => 'guest',
            'email' => 'guest.promote.'.uniqid('', true).'@example.com',
        ]);

        Sanctum::actingAs($guest);

        $response = $this->patchJson('/api/profile', [
            'role' => 'student',
            'profileSetupComplete' => true,
        ]);

        $response->assertForbidden();
        $guest->refresh();
        $this->assertSame('guest', $guest->role);
        $this->assertDatabaseMissing('students', ['user_id' => $guest->id]);
    }

    public function test_school_provisioning_still_creates_linked_student_and_setup_token(): void
    {
        $context = $this->createApprovedSchool();
        Sanctum::actingAs($context['user']);

        $email = 'provisioned.'.uniqid('', true).'@example.com';
        $response = $this->postJson('/api/school-students', [
            'email' => $email,
            'studentName' => 'Provisioned Student',
            'studentNumber' => '2026-001',
            'course' => 'BSIT',
            'yearLevel' => '3rd Year',
        ]);

        $response->assertCreated();
        $user = User::query()->where('email', $email)->first();
        $this->assertNotNull($user);
        $this->assertSame('student', $user->role);
        $this->assertTrue((bool) $user->must_change_password);
        $this->assertTrue((bool) $user->is_temporary);

        $student = Student::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($student);
        $this->assertSame($context['school']->id, $student->school_id);
        $this->assertSame('BSIT', $student->course);

        $this->assertTrue(
            AccountSetupToken::query()
                ->where('user_id', $user->id)
                ->whereNull('used_at')
                ->exists()
        );
    }

    public function test_existing_student_login_still_works(): void
    {
        $user = User::factory()->create([
            'role' => 'student',
            'email' => 'existing.student.'.uniqid('', true).'@example.com',
            'password' => Hash::make('Password1'),
            'is_active' => true,
            'must_change_password' => false,
            'profile_setup_complete' => true,
        ]);

        Student::query()->create([
            'user_id' => $user->id,
            'course' => 'BSIT',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'Password1',
        ]);

        $response->assertOk();
        $response->assertJsonPath('user.role', 'student');
        $this->assertNotEmpty($response->json('token'));
    }
}
