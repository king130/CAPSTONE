<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('internships', function (Blueprint $table) {
            $table->string('company_name')->nullable()->after('company_id');
            $table->json('eligible_courses')->nullable();
            $table->string('allowance')->nullable();
            $table->string('contact_info')->nullable();
            $table->string('approval_status')->nullable()->default('approved');
            $table->text('approval_notes')->nullable();
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->string('resume_url')->nullable();
            $table->json('documents')->nullable();
            $table->boolean('documents_pending')->default(false);
            $table->string('internship_title')->nullable();
            $table->string('student_name')->nullable();
            $table->string('student_email')->nullable();
            $table->string('student_course')->nullable();
            $table->foreignId('company_id')->nullable()->after('student_id')->constrained('companies')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropColumn([
                'resume_url', 'documents', 'documents_pending',
                'internship_title', 'student_name', 'student_email', 'student_course', 'company_id',
            ]);
        });

        Schema::table('internships', function (Blueprint $table) {
            $table->dropColumn([
                'company_name', 'eligible_courses', 'allowance', 'contact_info',
                'approval_status', 'approval_notes',
            ]);
        });
    }
};
