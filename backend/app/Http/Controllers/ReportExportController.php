<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\ApplicationAssessment;
use App\Models\OjtLog;
use App\Services\PermissionGate;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportController extends Controller
{
    public function __construct(private readonly PermissionGate $permissions)
    {
    }

    public function export(Request $request): StreamedResponse|Response
    {
        $user = $request->user();
        $appRole = $user->effectiveAppRole();

        if ($appRole !== 'admin' && ! $this->permissions->userCanAny($user, ['org.reports_export', 'org.reports_view', 'org.view_reports'])) {
            return response()->json(['message' => 'Missing permission to export reports.'], Response::HTTP_FORBIDDEN);
        }

        $data = $request->validate([
            'type' => ['required', 'in:placements,ojt,assessments'],
        ]);

        $school = $user->organizationSchool() ?? $user->school;
        $company = $user->organizationCompany() ?? $user->company;

        return match ($data['type']) {
            'placements' => $this->exportPlacements($appRole, $school, $company),
            'ojt' => $this->exportOjt($appRole, $school, $company),
            'assessments' => $this->exportAssessments($appRole, $school, $company),
        };
    }

    private function exportPlacements(string $appRole, $school, $company): StreamedResponse
    {
        $query = Application::query()
            ->with(['student.user:id,name,email', 'company:id,company_name', 'internship:id,title'])
            ->orderByDesc('updated_at');

        if ($appRole === 'school' && $school) {
            $query->whereHas('student', function ($q) use ($school) {
                $q->where('school_id', $school->id);
                if ($school->subscription_code) {
                    $q->orWhere('school_subscription_code', $school->subscription_code);
                }
            });
        } elseif ($appRole === 'company' && $company) {
            $query->where('company_id', $company->id);
        }

        $rows = $query->get()->map(fn (Application $app) => [
            $app->student?->user?->name ?? $app->student_name,
            $app->student?->user?->email ?? $app->student_email,
            $app->internship_title ?? $app->internship?->title,
            $app->company?->company_name ?? '',
            $app->status,
            $app->created_at?->toDateString(),
            $app->updated_at?->toDateString(),
        ]);

        return $this->csvResponse('placements.csv', [
            'Student Name', 'Email', 'Internship', 'Company', 'Status', 'Created At', 'Updated At',
        ], $rows->all());
    }

    private function exportOjt(string $appRole, $school, $company): StreamedResponse
    {
        $query = OjtLog::query()
            ->with(['student.user:id,name,email', 'application.company:id,company_name'])
            ->orderByDesc('log_date');

        if ($appRole === 'school' && $school) {
            $query->whereHas('student', function ($q) use ($school) {
                $q->where('school_id', $school->id);
                if ($school->subscription_code) {
                    $q->orWhere('school_subscription_code', $school->subscription_code);
                }
            });
        } elseif ($appRole === 'company' && $company) {
            $query->whereHas('application', fn ($q) => $q->where('company_id', $company->id));
        }

        $rows = $query->get()->map(fn (OjtLog $log) => [
            $log->student?->user?->name ?? '',
            $log->student?->school_name ?? '',
            $log->application?->company?->company_name ?? '',
            $log->log_date?->toDateString(),
            substr((string) $log->time_in, 0, 5),
            substr((string) $log->time_out, 0, 5),
            (string) $log->hours,
            $log->status,
            $log->tasks,
        ]);

        return $this->csvResponse('ojt-logs.csv', [
            'Student', 'School', 'Company', 'Date', 'Time In', 'Time Out', 'Hours', 'Status', 'Tasks',
        ], $rows->all());
    }

    private function exportAssessments(string $appRole, $school, $company): StreamedResponse
    {
        $query = ApplicationAssessment::query()
            ->with(['application.student.user:id,name,email', 'application.company:id,company_name', 'assessedBy:id,name'])
            ->orderByDesc('created_at');

        if ($appRole === 'school' && $school) {
            $query->whereHas('application.student', function ($q) use ($school) {
                $q->where('school_id', $school->id);
                if ($school->subscription_code) {
                    $q->orWhere('school_subscription_code', $school->subscription_code);
                }
            });
        } elseif ($appRole === 'company' && $company) {
            $query->whereHas('application', fn ($q) => $q->where('company_id', $company->id));
        }

        $rows = $query->get()->map(fn (ApplicationAssessment $assessment) => [
            $assessment->application?->student?->user?->name ?? $assessment->application?->student_name,
            $assessment->application?->internship_title,
            $assessment->application?->company?->company_name ?? '',
            $assessment->stage,
            (string) ($assessment->overall_score ?? ''),
            $assessment->assessedBy?->name ?? '',
            $assessment->comments,
            $assessment->created_at?->toDateString(),
        ]);

        return $this->csvResponse('assessments.csv', [
            'Student', 'Internship', 'Company', 'Stage', 'Overall Score', 'Assessed By', 'Comments', 'Created At',
        ], $rows->all());
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, string|null>>  $rows
     */
    private function csvResponse(string $filename, array $headers, array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
