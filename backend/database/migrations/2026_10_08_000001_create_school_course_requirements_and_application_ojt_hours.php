<?php

use App\Services\ApplicationRequiredOjtHoursBackfill;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_course_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->string('course_name');
            $table->string('course_name_key');
            $table->unsignedInteger('required_hours');
            $table->timestamps();

            $table->unique(['school_id', 'course_name_key'], 'school_course_requirements_school_course_unique');
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->unsignedInteger('required_ojt_hours')->nullable()->after('student_course');
        });

        app(ApplicationRequiredOjtHoursBackfill::class)->backfill();
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn('required_ojt_hours');
        });

        Schema::dropIfExists('school_course_requirements');
    }
};
