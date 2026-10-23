<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('internships', function (Blueprint $table) {
            if (! Schema::hasColumn('internships', 'host_required_hours')) {
                $table->unsignedInteger('host_required_hours')->nullable()->after('duration');
            }
        });

        if (! Schema::hasTable('application_hours_agreements')) {
            Schema::create('application_hours_agreements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('application_id')->constrained('applications')->cascadeOnDelete();
                $table->unsignedInteger('school_required_hours');
                $table->unsignedInteger('host_offered_hours');
                $table->unsignedInteger('proposed_hours')->nullable();
                $table->unsignedInteger('agreed_hours')->nullable();
                $table->string('status', 32)->default('pending'); // pending|agreed|declined
                $table->string('proposed_by_side', 16)->nullable(); // school|host
                $table->foreignId('proposed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('school_confirmed_at')->nullable();
                $table->foreignId('school_confirmed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('host_confirmed_at')->nullable();
                $table->foreignId('host_confirmed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('note')->nullable();
                $table->timestamps();

                $table->unique('application_id');
                $table->index('status');
            });
        }

        if (! Schema::hasTable('application_hours_agreement_events')) {
            Schema::create('application_hours_agreement_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('agreement_id')->constrained('application_hours_agreements')->cascadeOnDelete();
                $table->foreignId('application_id')->constrained('applications')->cascadeOnDelete();
                $table->string('action', 64);
                $table->string('from_status', 32)->nullable();
                $table->string('to_status', 32)->nullable();
                $table->unsignedInteger('proposed_hours')->nullable();
                $table->unsignedInteger('agreed_hours')->nullable();
                $table->foreignId('changed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('changed_by_side', 16)->nullable();
                $table->string('changed_by_role', 32)->nullable();
                $table->text('note')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('application_hours_agreement_events');
        Schema::dropIfExists('application_hours_agreements');

        Schema::table('internships', function (Blueprint $table) {
            if (Schema::hasColumn('internships', 'host_required_hours')) {
                $table->dropColumn('host_required_hours');
            }
        });
    }
};
