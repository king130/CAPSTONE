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
            if (! Schema::hasColumn('applications', 'tracker_stage')) {
                $table->string('tracker_stage', 40)->nullable()->after('status');
                $table->index('tracker_stage');
            }
        });

        Schema::table('application_assessments', function (Blueprint $table) {
            if (! Schema::hasColumn('application_assessments', 'locked_at')) {
                $table->timestamp('locked_at')->nullable()->after('comments');
            }
            if (! Schema::hasColumn('application_assessments', 'lock_reason')) {
                $table->text('lock_reason')->nullable()->after('locked_at');
            }
        });

        if (! Schema::hasTable('application_stage_events')) {
            Schema::create('application_stage_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('application_id')->constrained()->cascadeOnDelete();
                $table->string('from_stage', 40)->nullable();
                $table->string('to_stage', 40);
                $table->foreignId('changed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('changed_by_role', 40)->nullable();
                $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
                $table->string('organization_name')->nullable();
                $table->text('reason')->nullable();
                $table->boolean('is_rollback')->default(false);
                $table->timestamps();

                $table->index(['application_id', 'created_at']);
            });
        }

        $this->backfillTrackerStages();
    }

    public function down(): void
    {
        Schema::dropIfExists('application_stage_events');

        Schema::table('application_assessments', function (Blueprint $table) {
            if (Schema::hasColumn('application_assessments', 'lock_reason')) {
                $table->dropColumn('lock_reason');
            }
            if (Schema::hasColumn('application_assessments', 'locked_at')) {
                $table->dropColumn('locked_at');
            }
        });

        Schema::table('applications', function (Blueprint $table) {
            if (Schema::hasColumn('applications', 'tracker_stage')) {
                $table->dropIndex(['tracker_stage']);
                $table->dropColumn('tracker_stage');
            }
        });
    }

    private function backfillTrackerStages(): void
    {
        if (! Schema::hasColumn('applications', 'tracker_stage')) {
            return;
        }

        $apps = DB::table('applications')->select('id', 'status')->orderBy('id')->get();
        foreach ($apps as $app) {
            $status = strtolower(trim((string) $app->status));
            $stage = match ($status) {
                'accepted', 'approved', 'active' => 'accepted',
                'rejected', 'declined' => 'rejected',
                'school_rejected' => 'school_rejected',
                'endorsed' => 'endorsed',
                default => 'applied',
            };

            if ($stage === 'endorsed') {
                $hasCompletedInterview = DB::table('application_interviews')
                    ->where('application_id', $app->id)
                    ->where('status', 'completed')
                    ->exists();
                $hasOpenInterview = DB::table('application_interviews')
                    ->where('application_id', $app->id)
                    ->whereIn('status', ['proposed', 'confirmed'])
                    ->exists();
                $hasAssessment = DB::table('application_assessments')
                    ->where('application_id', $app->id)
                    ->exists();

                if ($hasAssessment) {
                    $stage = 'assessed';
                } elseif ($hasCompletedInterview) {
                    $stage = 'interviewed';
                } elseif ($hasOpenInterview) {
                    $stage = 'interview_scheduled';
                }
            }

            DB::table('applications')->where('id', $app->id)->update(['tracker_stage' => $stage]);
        }

        // Lock existing assessments so they are not treated as editable drafts.
        if (Schema::hasColumn('application_assessments', 'locked_at')) {
            DB::table('application_assessments')
                ->whereNull('locked_at')
                ->update(['locked_at' => DB::raw('COALESCE(updated_at, created_at, CURRENT_TIMESTAMP)')]);
        }
    }
};
