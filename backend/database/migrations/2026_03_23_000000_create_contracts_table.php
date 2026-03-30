<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('school_name');
            $table->foreignId('company_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('company_name');
            $table->string('requested_by_role', 20);
            $table->string('status', 32)->default('pending');
            $table->string('subject');
            $table->string('contract_type')->nullable();
            $table->string('moa_reference_no')->nullable();
            $table->text('purpose')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->unsignedInteger('internship_slots')->nullable();
            $table->text('student_programs')->nullable();
            $table->json('course_allocations')->nullable();
            $table->text('company_responsibilities')->nullable();
            $table->text('school_responsibilities')->nullable();
            $table->text('terms')->nullable();
            $table->string('school_contact_name')->nullable();
            $table->string('school_contact_email')->nullable();
            $table->string('company_contact_name')->nullable();
            $table->string('company_contact_email')->nullable();
            $table->text('notes')->nullable();
            $table->json('attachments')->nullable();
            $table->text('rejected_reason')->nullable();
            $table->text('cancelled_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancelled_by_role', 20)->nullable();
            $table->timestamps();

            $table->index(['school_user_id', 'company_user_id']);
            $table->index(['company_user_id', 'status']);
            $table->index(['school_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
