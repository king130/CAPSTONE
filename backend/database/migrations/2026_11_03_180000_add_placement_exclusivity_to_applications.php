<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            if (! Schema::hasColumn('applications', 'withdrawal_reason')) {
                $table->string('withdrawal_reason', 120)->nullable()->after('tracker_stage');
            }
            if (! Schema::hasColumn('applications', 'placement_closed_at')) {
                $table->timestamp('placement_closed_at')->nullable()->after('withdrawal_reason');
            }
            if (! Schema::hasColumn('applications', 'placement_close_reason')) {
                $table->string('placement_close_reason', 40)->nullable()->after('placement_closed_at');
            }
            if (! Schema::hasColumn('applications', 'active_placement_key')) {
                // Unique when set to student_id for an active accepted placement.
                // Multiple NULLs are allowed, so inactive applications do not conflict.
                $table->unsignedBigInteger('active_placement_key')->nullable()->after('placement_close_reason');
                $table->unique('active_placement_key', 'applications_active_placement_key_unique');
            }
        });

        // Backfill the lock key only for students with exactly one open accepted placement.
        // Interns with multiple accepted rows are left alone for manual review.
        $singles = DB::table('applications')
            ->select('student_id', DB::raw('MIN(id) as application_id'), DB::raw('COUNT(*) as total'))
            ->whereNull('placement_closed_at')
            ->where(function ($query) {
                $query->where('tracker_stage', 'accepted')
                    ->orWhere(function ($legacy) {
                        $legacy->where(function ($inner) {
                            $inner->whereNull('tracker_stage')->orWhere('tracker_stage', '');
                        })->whereIn('status', ['accepted', 'approved', 'active']);
                    })
                    ->orWhere(function ($both) {
                        $both->where('status', 'accepted')
                            ->where(function ($stage) {
                                $stage->whereNull('tracker_stage')->orWhere('tracker_stage', 'accepted');
                            });
                    });
            })
            ->groupBy('student_id')
            ->havingRaw('COUNT(*) = 1')
            ->get();

        foreach ($singles as $row) {
            DB::table('applications')
                ->where('id', $row->application_id)
                ->update(['active_placement_key' => $row->student_id]);
        }
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            if (Schema::hasColumn('applications', 'active_placement_key')) {
                $table->dropUnique('applications_active_placement_key_unique');
                $table->dropColumn('active_placement_key');
            }
            foreach (['placement_close_reason', 'placement_closed_at', 'withdrawal_reason'] as $column) {
                if (Schema::hasColumn('applications', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
