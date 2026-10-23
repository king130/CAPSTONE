<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_course_requirements', function (Blueprint $table) {
            if (! Schema::hasColumn('school_course_requirements', 'eligible_year_levels')) {
                $table->json('eligible_year_levels')->nullable()->after('required_hours');
            }
        });

        $default = json_encode(['3rd Year', '4th Year']);

        // Existing courses keep their hours; eligible years default to 3rd + 4th.
        DB::table('school_course_requirements')
            ->where(function ($query) {
                $query->whereNull('eligible_year_levels')
                    ->orWhere('eligible_year_levels', '')
                    ->orWhere('eligible_year_levels', '[]')
                    ->orWhere('eligible_year_levels', 'null');
            })
            ->update(['eligible_year_levels' => $default]);
    }

    public function down(): void
    {
        Schema::table('school_course_requirements', function (Blueprint $table) {
            if (Schema::hasColumn('school_course_requirements', 'eligible_year_levels')) {
                $table->dropColumn('eligible_year_levels');
            }
        });
    }
};
