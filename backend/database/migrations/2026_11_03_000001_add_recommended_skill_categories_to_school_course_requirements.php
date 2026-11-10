<?php

use App\Models\SchoolCourseRequirement;
use App\Support\CourseSkillCategoryDefaults;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_course_requirements', function (Blueprint $table) {
            $table->json('recommended_skill_categories')->nullable()->after('eligible_year_levels');
        });

        SchoolCourseRequirement::query()
            ->whereNull('recommended_skill_categories')
            ->orderBy('id')
            ->each(function (SchoolCourseRequirement $requirement): void {
                $defaults = CourseSkillCategoryDefaults::forCourseName((string) $requirement->course_name);
                if ($defaults === []) {
                    return;
                }
                $requirement->forceFill([
                    'recommended_skill_categories' => $defaults,
                ])->saveQuietly();
            });
    }

    public function down(): void
    {
        Schema::table('school_course_requirements', function (Blueprint $table) {
            $table->dropColumn('recommended_skill_categories');
        });
    }
};
