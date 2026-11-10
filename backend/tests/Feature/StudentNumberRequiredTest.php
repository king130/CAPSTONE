<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Permission;
use App\Models\Role;
use App\Models\School;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StudentNumberRequiredTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{user: User, organization: Organization, school: School}
     */
    private function createApprovedSchool(string $name = 'Number School'): array
    {
        foreach ([
            'manage_users' => 'Manage users',
            'org.manage_members' => 'Manage organization members',
            'org.manage_students' => 'Manage students',
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
            'name' => $name.' Org',
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
            Permission::query()->whereIn('key', ['manage_users', 'org.manage_members', 'org.manage_students'])->pluck('id')
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
            'profile' => [
                'officialSchoolEmail' => 'registrar@'.$this->schoolDomain($name),
            ],
        ])->save();

        $school = School::query()->create([
            'user_id' => $user->id,
            'organization_id' => $organization->id,
            'institution_name' => $name,
            'subscription_code' => 'NUM-'.strtoupper(substr(uniqid(), -4)),
            'verification_status' => 'approved',
        ]);

        return compact('user', 'organization', 'school');
    }

    private function schoolDomain(string $name): string
    {
        return strtolower(preg_replace('/[^a-z0-9]+/i', '', $name) ?: 'school').'.test';
    }

    public function test_school_create_rejects_empty_student_number(): void
    {
        $context = $this->createApprovedSchool('Empty Number School');
        Sanctum::actingAs($context['user']);
        $domain = $this->schoolDomain('Empty Number School');

        $response = $this->postJson('/api/school-students', [
            'email' => 'empty.number@'.$domain,
            'studentName' => 'Empty Number',
            'studentNumber' => '   ',
            'course' => 'BSIT',
            'yearLevel' => '4th Year',
        ]);

        $response->assertStatus(422);
        $this->assertTrue(
            isset($response->json('errors')['studentNumber'])
            || str_contains((string) $response->json('message'), 'Student number')
        );
    }

    public function test_school_create_rejects_duplicate_student_number_in_same_school(): void
    {
        $context = $this->createApprovedSchool('Dup Number School');
        Sanctum::actingAs($context['user']);
        $domain = $this->schoolDomain('Dup Number School');

        $first = $this->postJson('/api/school-students', [
            'email' => 'first.dup@'.$domain,
            'studentName' => 'First Student',
            'studentNumber' => 'DUP-100',
            'course' => 'BSIT',
            'yearLevel' => '4th Year',
        ]);
        $first->assertCreated();

        $second = $this->postJson('/api/school-students', [
            'email' => 'second.dup@'.$domain,
            'studentName' => 'Second Student',
            'studentNumber' => 'dup-100',
            'course' => 'BSIT',
            'yearLevel' => '4th Year',
        ]);

        $second->assertStatus(422);
        $second->assertJsonFragment([
            'message' => 'That student number is already assigned to another student in this school. Use a different number.',
        ]);
        $payload = json_encode($second->json()) ?: '';
        $this->assertStringNotContainsString('First Student', $payload);
        $this->assertStringNotContainsString('first.dup@'.$domain, $payload);
    }

    public function test_school_create_accepts_valid_student_number(): void
    {
        $context = $this->createApprovedSchool('Valid Number School');
        Sanctum::actingAs($context['user']);
        $domain = $this->schoolDomain('Valid Number School');

        $response = $this->postJson('/api/school-students', [
            'email' => 'valid.number@'.$domain,
            'studentName' => 'Valid Student',
            'studentNumber' => '2026-0042',
            'course' => 'BSIT',
            'yearLevel' => '4th Year',
        ]);

        $response->assertCreated();
        $this->assertSame('2026-0042', $response->json('data.studentNumber'));
    }

    public function test_api_omitting_student_number_fails_validation(): void
    {
        $context = $this->createApprovedSchool('Omit Number School');
        Sanctum::actingAs($context['user']);
        $domain = $this->schoolDomain('Omit Number School');

        $response = $this->postJson('/api/school-students', [
            'email' => 'omit.number@'.$domain,
            'studentName' => 'Omit Number',
            'course' => 'BSIT',
            'yearLevel' => '4th Year',
        ]);

        $response->assertStatus(422);
    }
}
