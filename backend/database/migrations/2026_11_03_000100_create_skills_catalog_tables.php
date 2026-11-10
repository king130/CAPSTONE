<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skills', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('normalized_name')->index();
            $table->string('category', 64)->index();
            $table->string('status', 32)->default('approved')->index(); // approved | pending_review | rejected
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('merged_into_skill_id')->nullable()->constrained('skills')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('student_skill', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('skill_id')->constrained('skills')->cascadeOnDelete();
            $table->string('level', 32)->default('beginner'); // beginner | intermediate | advanced
            $table->timestamps();
            $table->unique(['student_id', 'skill_id']);
        });

        Schema::create('internship_skill', function (Blueprint $table) {
            $table->id();
            $table->foreignId('internship_id')->constrained('internships')->cascadeOnDelete();
            $table->foreignId('skill_id')->constrained('skills')->cascadeOnDelete();
            $table->string('importance', 32)->default('required'); // required | nice_to_have
            $table->timestamps();
            $table->unique(['internship_id', 'skill_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('internship_skill');
        Schema::dropIfExists('student_skill');
        Schema::dropIfExists('skills');
    }
};
