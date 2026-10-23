<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ojt_dtr_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('submitted_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('previous_submission_id')->nullable()->constrained('ojt_dtr_submissions')->nullOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('submitted_hours', 6, 2);
            $table->string('status', 20)->default('pending');
            $table->text('remarks')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->string('file_path');
            $table->string('file_name');
            $table->string('file_mime', 150)->nullable();
            $table->unsignedInteger('file_size')->nullable();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['application_id', 'status']);
            $table->index(['student_id', 'status']);
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ojt_dtr_submissions');
    }
};
