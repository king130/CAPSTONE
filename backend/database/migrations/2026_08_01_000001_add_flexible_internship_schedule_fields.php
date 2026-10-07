<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('internships', function (Blueprint $table) {
            $table->string('schedule_type')->nullable()->after('schedule');
            $table->decimal('weekly_hours', 6, 2)->nullable()->after('schedule_type');
            $table->json('schedule_days')->nullable()->after('weekly_hours');
            $table->string('time_in')->nullable()->after('schedule_days');
            $table->string('time_out')->nullable()->after('time_in');
            $table->string('timezone')->default('Asia/Manila')->after('time_out');
            $table->boolean('is_flexible')->default(false)->after('timezone');
        });
    }

    public function down(): void
    {
        Schema::table('internships', function (Blueprint $table) {
            $table->dropColumn([
                'schedule_type',
                'weekly_hours',
                'schedule_days',
                'time_in',
                'time_out',
                'timezone',
                'is_flexible',
            ]);
        });
    }
};
