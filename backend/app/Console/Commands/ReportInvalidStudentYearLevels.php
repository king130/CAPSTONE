<?php

namespace App\Console\Commands;

use App\Models\Student;
use App\Support\StudentYearLevel;
use Illuminate\Console\Command;

/**
 * Safe audit of student year_level values that are not 3rd/4th Year.
 * Does not overwrite data — use the report to correct records manually or via a follow-up script.
 */
class ReportInvalidStudentYearLevels extends Command
{
    protected $signature = 'students:report-invalid-year-levels {--fix : Also rewrite values that normalize cleanly to 3rd/4th Year}';

    protected $description = 'List (and optionally normalize) student year_level values outside 3rd Year / 4th Year';

    public function handle(): int
    {
        $fix = (bool) $this->option('fix');
        $invalid = [];
        $fixed = 0;

        Student::query()->orderBy('id')->chunkById(200, function ($students) use (&$invalid, &$fixed, $fix) {
            foreach ($students as $student) {
                $raw = $student->year_level;
                if ($raw === null || trim((string) $raw) === '') {
                    $invalid[] = [
                        'id' => $student->id,
                        'user_id' => $student->user_id,
                        'year_level' => '(empty)',
                        'action' => 'needs manual assignment',
                    ];

                    continue;
                }

                if (StudentYearLevel::isValid((string) $raw)) {
                    $normalized = StudentYearLevel::normalize((string) $raw);
                    if ($fix && $normalized !== null && $normalized !== $raw) {
                        $student->year_level = $normalized;
                        $student->save();
                        if ($student->user) {
                            $profile = $student->user->profile ?? [];
                            $profile['yearLevel'] = $normalized;
                            $student->user->profile = $profile;
                            $student->user->save();
                        }
                        $fixed++;
                    }

                    continue;
                }

                $normalized = StudentYearLevel::normalize((string) $raw);
                if ($normalized !== null) {
                    if ($fix) {
                        $student->year_level = $normalized;
                        $student->save();
                        if ($student->user) {
                            $profile = $student->user->profile ?? [];
                            $profile['yearLevel'] = $normalized;
                            $student->user->profile = $profile;
                            $student->user->save();
                        }
                        $fixed++;
                        $invalid[] = [
                            'id' => $student->id,
                            'user_id' => $student->user_id,
                            'year_level' => (string) $raw,
                            'action' => "normalized to {$normalized}",
                        ];
                    } else {
                        $invalid[] = [
                            'id' => $student->id,
                            'user_id' => $student->user_id,
                            'year_level' => (string) $raw,
                            'action' => "can normalize to {$normalized} (re-run with --fix)",
                        ];
                    }

                    continue;
                }

                $invalid[] = [
                    'id' => $student->id,
                    'user_id' => $student->user_id,
                    'year_level' => (string) $raw,
                    'action' => 'cannot auto-map; set manually to 3rd Year or 4th Year',
                ];
            }
        });

        if ($invalid === []) {
            $this->info('All student year_level values are valid (3rd Year / 4th Year) or empty-only rows were none.');
        } else {
            $this->table(['student_id', 'user_id', 'year_level', 'action'], $invalid);
            $this->warn(count($invalid).' record(s) need attention.');
        }

        if ($fix) {
            $this->info("Normalized {$fixed} record(s).");
        } else {
            $this->line('No data was overwritten. Re-run with --fix to normalize unambiguous values only.');
        }

        return self::SUCCESS;
    }
}
