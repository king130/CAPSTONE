<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Copies an explicitly configured organization required-hours setting onto
 * existing applications. Does not invent the 500-hour fallback.
 */
class ApplicationRequiredOjtHoursBackfill
{
    public function backfill(): int
    {
        $updated = 0;

        $rows = DB::table('applications')
            ->join('students', 'students.id', '=', 'applications.student_id')
            ->leftJoin('schools', 'schools.id', '=', 'students.school_id')
            ->leftJoin('organizations as school_organizations', 'school_organizations.id', '=', 'schools.organization_id')
            ->leftJoin('organizations as student_organizations', 'student_organizations.id', '=', 'students.organization_id')
            ->whereNull('applications.required_ojt_hours')
            ->select([
                'applications.id as application_id',
                'school_organizations.settings as school_settings',
                'school_organizations.type as school_org_type',
                'student_organizations.settings as student_settings',
                'student_organizations.type as student_org_type',
            ])
            ->orderBy('applications.id')
            ->get();

        foreach ($rows as $row) {
            $hours = $this->configuredHours($row->school_settings, $row->school_org_type)
                ?? $this->configuredHours($row->student_settings, $row->student_org_type);

            if ($hours === null) {
                continue;
            }

            DB::table('applications')
                ->where('id', $row->application_id)
                ->whereNull('required_ojt_hours')
                ->update([
                    'required_ojt_hours' => $hours,
                    'updated_at' => now(),
                ]);
            $updated++;
        }

        return $updated;
    }

    private function configuredHours(mixed $settings, mixed $organizationType): ?int
    {
        if ($organizationType !== null && $organizationType !== 'school') {
            return null;
        }

        if (is_string($settings)) {
            $decoded = json_decode($settings, true);
            $settings = is_array($decoded) ? $decoded : null;
        }

        if (! is_array($settings)) {
            return null;
        }

        foreach (['required_ojt_hours', 'requiredHours'] as $key) {
            if (! array_key_exists($key, $settings) || ! is_numeric($settings[$key])) {
                continue;
            }

            $hours = (int) $settings[$key];
            if ($hours >= 1) {
                return $hours;
            }
        }

        return null;
    }
}
