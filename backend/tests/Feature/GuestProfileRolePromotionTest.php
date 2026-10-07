<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GuestProfileRolePromotionTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_promote_to_school_via_profile(): void
    {
        $guest = User::factory()->create([
            'role' => 'guest',
            'email' => 'guest.school@example.com',
        ]);

        Sanctum::actingAs($guest);

        $orgCountBefore = Organization::query()->count();
        $membershipCountBefore = OrganizationMembership::query()->count();

        $response = $this->patchJson('/api/profile', [
            'role' => 'school',
        ]);

        $response->assertForbidden();
        $response->assertJsonFragment([
            'message' => 'School and company accounts must be created through registration.',
        ]);

        $guest->refresh();
        $this->assertSame('guest', $guest->role);
        $this->assertSame($orgCountBefore, Organization::query()->count());
        $this->assertSame($membershipCountBefore, OrganizationMembership::query()->count());
        $this->assertDatabaseMissing('schools', ['user_id' => $guest->id]);
    }

    public function test_guest_cannot_promote_to_company_via_profile(): void
    {
        $guest = User::factory()->create([
            'role' => 'guest',
            'email' => 'guest.company@example.com',
        ]);

        Sanctum::actingAs($guest);

        $orgCountBefore = Organization::query()->count();
        $membershipCountBefore = OrganizationMembership::query()->count();

        $response = $this->patchJson('/api/profile', [
            'role' => 'company',
        ]);

        $response->assertForbidden();
        $response->assertJsonFragment([
            'message' => 'School and company accounts must be created through registration.',
        ]);

        $guest->refresh();
        $this->assertSame('guest', $guest->role);
        $this->assertSame($orgCountBefore, Organization::query()->count());
        $this->assertSame($membershipCountBefore, OrganizationMembership::query()->count());
        $this->assertDatabaseMissing('companies', ['user_id' => $guest->id]);
    }

    public function test_guest_cannot_promote_to_student_via_profile(): void
    {
        $guest = User::factory()->create([
            'role' => 'guest',
            'email' => 'guest.student@example.com',
            'profile' => [
                'schoolName' => 'Test School',
                'course' => 'BSIT',
                'yearLevel' => '4th Year',
            ],
        ]);

        Sanctum::actingAs($guest);

        $response = $this->patchJson('/api/profile', [
            'role' => 'student',
            'profileSetupComplete' => true,
        ]);

        $response->assertForbidden();
        $response->assertJsonFragment([
            'message' => 'Student accounts are created by your school. Use your school-issued setup link or login credentials.',
        ]);

        $guest->refresh();
        $this->assertSame('guest', $guest->role);
        $this->assertDatabaseMissing('students', [
            'user_id' => $guest->id,
        ]);
    }

    public function test_non_guest_cannot_change_role_via_profile(): void
    {
        $student = User::factory()->create([
            'role' => 'student',
            'email' => 'already.student@example.com',
        ]);

        Sanctum::actingAs($student);

        $response = $this->patchJson('/api/profile', [
            'role' => 'school',
        ]);

        $response->assertOk();

        $student->refresh();
        $this->assertSame('student', $student->role);
        $this->assertDatabaseMissing('schools', ['user_id' => $student->id]);
        $this->assertDatabaseMissing('organizations', ['owner_user_id' => $student->id]);
    }
}
