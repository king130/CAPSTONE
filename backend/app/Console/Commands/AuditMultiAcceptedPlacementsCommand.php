<?php

namespace App\Console\Commands;

use App\Models\Application;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AuditMultiAcceptedPlacementsCommand extends Command
{
    protected $signature = 'placements:audit-multi-accepted';

    protected $description = 'List interns who currently have more than one accepted (non-closed) placement. Does not change data.';

    public function handle(): int
    {
        $accepted = Application::query()
            ->with(['student.user:id,name,email'])
            ->whereNull('placement_closed_at')
            ->where(function ($query) {
                $query->where('tracker_stage', 'accepted')
                    ->orWhere(function ($legacy) {
                        $legacy->whereNull('tracker_stage')
                            ->whereIn('status', ['accepted', 'approved', 'active']);
                    })
                    ->orWhere(function ($status) {
                        $status->where('status', 'accepted')
                            ->where(function ($stage) {
                                $stage->whereNull('tracker_stage')
                                    ->orWhere('tracker_stage', 'accepted');
                            });
                    });
            })
            ->orderBy('student_id')
            ->orderBy('id')
            ->get()
            ->groupBy('student_id')
            ->filter(fn ($group) => $group->count() > 1);

        if ($accepted->isEmpty()) {
            $this->info('No interns have more than one open accepted placement.');

            return self::SUCCESS;
        }

        $this->warn('Found '.$accepted->count().' intern(s) with multiple accepted placements (not changed):');
        $this->newLine();

        foreach ($accepted as $studentId => $apps) {
            $student = $apps->first()?->student;
            $this->line(sprintf(
                '- student_id=%s name=%s email=%s count=%s apps=[%s] titles=%s',
                $studentId,
                $student?->user?->name ?: ($student?->user_id ?: 'unknown'),
                $student?->user?->email ?: 'n/a',
                $apps->count(),
                $apps->pluck('id')->implode(','),
                $apps->map(fn ($app) => $app->internship_title ?: ('#'.$app->internship_id))->implode(' | ')
            ));
        }

        return self::SUCCESS;
    }
}
