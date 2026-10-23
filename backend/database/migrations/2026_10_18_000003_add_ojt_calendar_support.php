<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('placement_schedules', function (Blueprint $table) {
            if (! Schema::hasColumn('placement_schedules', 'ojt_start_date')) {
                $table->date('ojt_start_date')->nullable()->after('source');
            }
            if (! Schema::hasColumn('placement_schedules', 'ojt_start_locked_at')) {
                $table->timestamp('ojt_start_locked_at')->nullable()->after('ojt_start_date');
            }
        });

        if (! Schema::hasTable('placement_schedule_versions')) {
            Schema::create('placement_schedule_versions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('application_id')->constrained()->cascadeOnDelete();
                $table->json('slots');
                $table->date('effective_from');
                $table->string('source', 40)->nullable();
                $table->timestamps();

                $table->index(['application_id', 'effective_from']);
            });
        }

        if (! Schema::hasTable('public_holidays')) {
            Schema::create('public_holidays', function (Blueprint $table) {
                $table->id();
                $table->date('date');
                $table->string('name');
                $table->string('type', 40)->default('national'); // national|special|local
                $table->string('scope')->nullable(); // optional LGU/org scope
                $table->timestamps();

                $table->unique(['date', 'name']);
                $table->index('date');
            });
        }

        // Backfill versions from existing active schedules (do not invent start dates).
        if (Schema::hasTable('placement_schedules') && Schema::hasTable('placement_schedule_versions')) {
            $rows = DB::table('placement_schedules')->orderBy('id')->get();
            foreach ($rows as $row) {
                $exists = DB::table('placement_schedule_versions')
                    ->where('application_id', $row->application_id)
                    ->exists();
                if ($exists) {
                    continue;
                }
                $slots = json_decode((string) $row->active_slots, true);
                if (! is_array($slots) || $slots === []) {
                    continue;
                }
                DB::table('placement_schedule_versions')->insert([
                    'application_id' => $row->application_id,
                    'slots' => json_encode($slots),
                    'effective_from' => $row->ojt_start_date
                        ?? substr((string) $row->created_at, 0, 10)
                        ?? now('Asia/Manila')->toDateString(),
                    'source' => $row->source,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('placement_schedule_versions');
        Schema::dropIfExists('public_holidays');

        Schema::table('placement_schedules', function (Blueprint $table) {
            if (Schema::hasColumn('placement_schedules', 'ojt_start_locked_at')) {
                $table->dropColumn('ojt_start_locked_at');
            }
            if (Schema::hasColumn('placement_schedules', 'ojt_start_date')) {
                $table->dropColumn('ojt_start_date');
            }
        });
    }
};
