<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\School;
use App\Models\Skill;
use App\Models\Student;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StudentSkillsOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private function createSchool(): School
    {
        $owner = User::factory()->create(['role' => 'school']);
        $subscription = Subscription::query()->create([
            'plan' => 'standard',
            'status' => 'active',
            'billing_cycle' => 'monthly',
        ]);
        $organization = Organization::query()->create([
            'name' => 'Skills Ownership School',
            'type' => 'school',
            'subscription_id' => $subscription->id,
            'owner_user_id' => $owner->id,
            'is_active' => true,
        ]);

        return School::query()->create([
            'user_id' => $owner->id,
            'organization_id' => $organization->id,
            'institution_name' => $organization->name,
            'subscription_code' => 'SKL-OWN-1',
            'verification_status' => 'approved',
        ]);
    }

    private function createStudent(School $school, string $email = 'intern.skills@example.com'): User
    {
        $user = User::factory()->create([
            'role' => 'student',
            'email' => $email,
            'name' => 'Skills Intern',
        ]);
        Student::query()->create([
            'user_id' => $user->id,
            'school_id' => $school->id,
            'school_name' => $school->institution_name,
            'course' => 'BS Information Technology',
            'year_level' => '3rd Year',
            'student_id_number' => 'SN-'.uniqid(),
        ]);

        return $user->fresh(['student']);
    }

    private function approvedSkill(string $name = 'JavaScript'): Skill
    {
        return Skill::query()->create([
            'name' => $name,
            'normalized_name' => strtolower($name),
            'category' => 'IT & Software',
            'status' => Skill::STATUS_APPROVED,
        ]);
    }

    public function test_student_can_save_and_read_own_skills(): void
    {
        $school = $this->createSchool();
        $user = $this->createStudent($school);
        $skill = $this->approvedSkill();

        Sanctum::actingAs($user);

        $save = $this->putJson('/api/student/skills', [
            'skills' => [
                ['id' => $skill->id, 'level' => 'intermediate'],
            ],
        ]);
        $save->assertOk();
        $save->assertJsonPath('data.0.id', (string) $skill->id);
        $save->assertJsonPath('data.0.level', 'intermediate');

        $show = $this->getJson('/api/student/skills');
        $show->assertOk();
        $show->assertJsonCount(1, 'data');
        $show->assertJsonPath('data.0.name', 'JavaScript');
    }

    public function test_non_student_cannot_modify_student_skills(): void
    {
        $school = $this->createSchool();
        $owner = $school->user;
        $skill = $this->approvedSkill('Excel');

        Sanctum::actingAs($owner);

        $this->putJson('/api/student/skills', [
            'skills' => [['id' => $skill->id, 'level' => 'beginner']],
        ])->assertForbidden();

        $this->getJson('/api/student/skills')->assertForbidden();
    }

    public function test_student_cannot_exceed_skill_limit(): void
    {
        $school = $this->createSchool();
        $user = $this->createStudent($school, 'limit.skills@example.com');
        $ids = [];
        for ($i = 0; $i < Skill::MAX_STUDENT + 1; $i++) {
            $ids[] = ['id' => $this->approvedSkill('Skill '.$i)->id, 'level' => 'beginner'];
        }

        Sanctum::actingAs($user);

        $this->putJson('/api/student/skills', ['skills' => $ids])
            ->assertStatus(422);
    }

    public function test_custom_skill_duplicate_of_approved_is_rejected(): void
    {
        $school = $this->createSchool();
        $user = $this->createStudent($school, 'custom.skills@example.com');
        $this->approvedSkill('Python');

        Sanctum::actingAs($user);

        $this->postJson('/api/skills', [
            'name' => 'Python',
            'category' => 'IT & Software',
        ])->assertStatus(409);
    }
}
