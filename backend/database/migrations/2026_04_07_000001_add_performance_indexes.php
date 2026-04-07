<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->index(['company_id', 'created_at'], 'applications_company_created_idx');
            $table->index(['student_id', 'created_at'], 'applications_student_created_idx');
            $table->index(['status', 'created_at'], 'applications_status_created_idx');
        });

        Schema::table('internships', function (Blueprint $table) {
            $table->index(['company_id', 'status', 'created_at'], 'internships_company_status_created_idx');
            $table->index(['school_id', 'status', 'created_at'], 'internships_school_status_created_idx');
            $table->index(['host_type', 'status', 'created_at'], 'internships_host_status_created_idx');
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->index(['status', 'created_at'], 'contracts_status_created_idx');
            $table->index(['company_user_id', 'created_at'], 'contracts_company_created_idx');
            $table->index(['school_user_id', 'created_at'], 'contracts_school_created_idx');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->index(['school_id', 'created_at'], 'students_school_created_idx');
            $table->index(['school_subscription_code', 'created_at'], 'students_subscription_created_idx');
        });

        Schema::table('schools', function (Blueprint $table) {
            $table->index('subscription_code', 'schools_subscription_code_idx');
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropIndex('schools_subscription_code_idx');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->dropIndex('students_subscription_created_idx');
            $table->dropIndex('students_school_created_idx');
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->dropIndex('contracts_school_created_idx');
            $table->dropIndex('contracts_company_created_idx');
            $table->dropIndex('contracts_status_created_idx');
        });

        Schema::table('internships', function (Blueprint $table) {
            $table->dropIndex('internships_host_status_created_idx');
            $table->dropIndex('internships_school_status_created_idx');
            $table->dropIndex('internships_company_status_created_idx');
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->dropIndex('applications_status_created_idx');
            $table->dropIndex('applications_student_created_idx');
            $table->dropIndex('applications_company_created_idx');
        });
    }
};
