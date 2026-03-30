<?php

namespace App\Http\Controllers;

use App\Models\School;
use App\Models\SchoolReport;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class SchoolReportController extends Controller
{
    public function index(Request $request)
    {
        $school = $this->resolveSchool($request->user());

        $reports = SchoolReport::query()
            ->where('school_id', $school->id)
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (SchoolReport $report) => $this->transform($report))
            ->values()
            ->all();

        return response()->json(['data' => $reports]);
    }

    public function store(Request $request)
    {
        $school = $this->resolveSchool($request->user());

        $data = $request->validate([
            'reportType' => ['required', 'string', 'max:100'],
            'studentName' => ['required', 'string', 'max:255'],
            'internshipPeriodStart' => ['required', 'date'],
            'internshipPeriodEnd' => ['required', 'date'],
            'companyName' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::in(['draft', 'pending'])],
        ]);

        $status = $data['status'] === 'draft' ? 'draft' : 'pending';

        $report = SchoolReport::query()->create([
            'school_id' => $school->id,
            'organization_id' => $school->organization_id,
            'report_type' => $data['reportType'],
            'student_name' => $data['studentName'],
            'internship_period_start' => $data['internshipPeriodStart'],
            'internship_period_end' => $data['internshipPeriodEnd'],
            'company_name' => $data['companyName'],
            'company_status' => $status,
            'school_status' => null,
            'submitted_at' => $status === 'draft' ? null : now(),
        ]);

        return response()->json([
            'message' => $status === 'draft' ? 'Draft saved.' : 'Report submitted.',
            'data' => $this->transform($report),
        ], Response::HTTP_CREATED);
    }

    private function resolveSchool(?User $user): School
    {
        $school = $user?->school;
        if (! $school || $user?->role !== 'school') {
            abort(Response::HTTP_FORBIDDEN, 'Only schools can manage reports.');
        }

        return $school;
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(SchoolReport $report): array
    {
        return [
            'id' => (string) $report->id,
            'schoolId' => (string) $report->school_id,
            'reportType' => $report->report_type,
            'studentName' => $report->student_name,
            'internshipPeriodStart' => $report->internship_period_start?->format('Y-m-d'),
            'internshipPeriodEnd' => $report->internship_period_end?->format('Y-m-d'),
            'companyName' => $report->company_name,
            'companyStatus' => $report->company_status,
            'schoolStatus' => $report->school_status,
            'submittedAt' => $report->submitted_at?->toIso8601String(),
            'createdAt' => $report->created_at?->toIso8601String(),
            'updatedAt' => $report->updated_at?->toIso8601String(),
        ];
    }
}
