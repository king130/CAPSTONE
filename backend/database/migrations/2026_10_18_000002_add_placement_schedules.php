<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('internships', function (Blueprint $table) {
            if (! Schema::hasColumn('internships', 'schedule_slots')) {
                $table->json('schedule_slots')->nullable()->after('time_out');
            }
        });

        if (! Schema::hasTable('placement_schedules')) {
            Schema::create('placement_schedules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('application_id')->constrained()->cascadeOnDelete();
                $table->json('active_slots');
                $table->string('status', 20)->default('default'); // default|requested|approved|declined
                $table->string('source', 40)->default('posting_default'); // posting_default|system_default|approved_request
                $table->timestamps();

                $table->unique('application_id');
                $table->index('status');
            });
        }

        if (! Schema::hasTable('placement_schedule_requests')) {
            Schema::create('placement_schedule_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('application_id')->constrained()->cascadeOnDelete();
                $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->json('requested_slots');
                $table->text('reason');
                $table->string('status', 20)->default('requested'); // requested|approved|declined
                $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('decided_by_role', 40)->nullable();
                $table->timestamp('decided_at')->nullable();
                $table->text('decision_note')->nullable();
                $table->timestamps();

                $table->index(['application_id', 'status']);
                $table->index(['application_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('placement_schedule_requests');
        Schema::dropIfExists('placement_schedules');

        Schema::table('internships', function (Blueprint $table) {
            if (Schema::hasColumn('internships', 'schedule_slots')) {
                $table->dropColumn('schedule_slots');
            }
        });
    }
};
