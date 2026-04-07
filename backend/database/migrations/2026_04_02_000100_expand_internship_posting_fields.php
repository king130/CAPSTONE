<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('internships', function (Blueprint $table) {
            $table->string('industry')->nullable()->after('company_name');
            $table->date('start_date')->nullable()->after('allowance');
            $table->date('end_date')->nullable()->after('start_date');
            $table->string('schedule')->nullable()->after('end_date');
            $table->text('tasks')->nullable()->after('schedule');
            $table->json('required_skills')->nullable()->after('tasks');
            $table->text('intern_gains')->nullable()->after('required_skills');
            $table->json('required_documents')->nullable()->after('intern_gains');
            $table->text('application_instructions')->nullable()->after('required_documents');
        });
    }

    public function down(): void
    {
        Schema::table('internships', function (Blueprint $table) {
            $table->dropColumn([
                'industry',
                'start_date',
                'end_date',
                'schedule',
                'tasks',
                'required_skills',
                'intern_gains',
                'required_documents',
                'application_instructions',
            ]);
        });
    }
};
