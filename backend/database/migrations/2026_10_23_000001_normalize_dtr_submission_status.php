<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('ojt_dtr_submissions', 'review_stage')) {
            Schema::table('ojt_dtr_submissions', function (Blueprint $table) {
                $table->string('review_stage', 32)->nullable()->after('status');
            });
        }

        DB::table('ojt_dtr_submissions')
            ->where('status', 'pending_company')
            ->update(['status' => 'submitted', 'review_stage' => 'host']);

        DB::table('ojt_dtr_submissions')
            ->whereIn('status', ['pending_coordinator', 'pending'])
            ->update(['status' => 'submitted', 'review_stage' => 'school']);
    }

    public function down(): void
    {
        DB::table('ojt_dtr_submissions')
            ->where('status', 'submitted')
            ->where('review_stage', 'host')
            ->update(['status' => 'pending_company']);

        DB::table('ojt_dtr_submissions')
            ->where('status', 'submitted')
            ->where('review_stage', 'school')
            ->update(['status' => 'pending_coordinator']);

        if (Schema::hasColumn('ojt_dtr_submissions', 'review_stage')) {
            Schema::table('ojt_dtr_submissions', function (Blueprint $table) {
                $table->dropColumn('review_stage');
            });
        }
    }
};
