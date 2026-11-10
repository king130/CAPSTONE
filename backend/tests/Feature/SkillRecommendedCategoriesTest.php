<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\School;
use App\Models\SchoolCourseRequirement;
use App\Models\Student;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SkillRecommendedCategoriesTest extends TestCase
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
            'name' => 'Skills School',
            'type' => 'school',
            'subscription_id' => $subscription->id,
            'owner_user_id' => $owner->id,
            'is_active' => true,
        ]);

        return School::query()->create([
            'user_id' => $owner->id,
            'organization_id' => $organization->id,
            'institution_name' => $organization->name,
            'subscription_code' => 'SKL-001',
            'verification_status' => 'approved',
        ]);
    }

    public function test_skills_index_includes_recommended_categories_for_student_course(): void
    {
        $school = $this->createSchool();
        SchoolCourseRequirement::query()->create([
            'school_id' => $school->id,
            'course_name' => 'BS Information Technology',
            'course_name_key' => 'bs information technology',
            'required_hours' => 500,
            'eligible_year_levels' => ['3rd Year', '4th Year'],
            'recommended_skill_categories' => ['IT & Software', 'Communication'],
        ]);

        $user = User::factory()->create(['role' => 'student']);
        Student::query()->create([
            'user_id' => $user->id,
            'school_id' => $school->id,
            'course' => 'BS Information Technology',
            'year_level' => '3rd Year',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/skills');

        $response->assertOk();
        $response->assertJsonPath('recommendedCategories', ['IT & Software', 'Communication']);
    }

    public function test_skills_index_returns_empty_recommended_categories_when_course_unmapped(): void
    {
        $user = User::factory()->create(['role' => 'student']);
        Student::query()->create([
            'user_id' => $user->id,
            'course' => 'Bachelor of Religious Studies',
            'year_level' => '3rd Year',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/skills');

        $response->assertOk();
        $response->assertJsonPath('recommendedCategories', []);
    }
}
