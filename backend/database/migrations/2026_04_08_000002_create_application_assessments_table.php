<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assessed_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('stage', 20)->default('pre_screen');
            $table->json('rubric_scores')->nullable();
            $table->decimal('overall_score', 5, 2)->nullable();
            $table->text('comments')->nullable();
            $table->timestamps();

            $table->index(['application_id', 'stage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_assessments');
    }
};
