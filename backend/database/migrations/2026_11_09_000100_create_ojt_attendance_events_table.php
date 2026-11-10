<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ojt_attendance_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('applications')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            // DATE only (no time). Application code uses Asia/Manila calendar dates, same as ojt_logs.log_date.
            $table->date('date');
            $table->string('type', 32); // absent_excused | absent_unexcused
            $table->text('reason');
            $table->foreignId('marked_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['application_id', 'date']);
            $table->index(['student_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ojt_attendance_events');
    }
};
