<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\ApplicationAssessment;
use App\Models\Contract;
use App\Models\OjtLog;
use App\Models\Student;
use App\Services\PermissionGate;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class KpiReportController extends Controller
{
    public function __construct(private readonly PermissionGate $permissions)
    {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $appRole = $user->effectiveAppRole();

        if ($appRole !== 'admin' && ! $this->permissions->userCanAny($user, ['org.reports_view', 'org.view_reports'])) {
            return response()->json(['message' => 'Missing permission to view KPI reports.'], Response::HTTP_FORBIDDEN);
        }

        $school = $user->organizationSchool() ?? $user->school;
        $company = $user->organizationCompany() ?? $user->company;

        $applicationsQuery = Application::query();
        $contractsQuery = Contract::query();
        $ojtQuery = OjtLog::query()->where('status', 'approved');
        $assessmentQuery = ApplicationAssessment::query();
        $studentsQuery = Student::query();

        if ($appRole === 'school' && $school) {
            $applicationsQuery->whereHas('student', function ($q) use ($school) {
                $q->where('school_id', $school->id);
                if ($school->subscription_code) {
                    $q->orWhere('school_subscription_code', $school->subscription_code);
                }
            });
            $contractsQuery->where('school_user_id', $school->user_id);
            $ojtQuery->whereHas('student', function ($q) use ($school) {
                $q->where('school_id', $school->id);
                if ($school->subscription_code) {
                    $q->orWhere('school_subscription_code', $school->subscription_code);
                }
            });
            $assessmentQuery->whereHas('application.student', function ($q) use ($school) {
                $q->where('school_id', $school->id);
                if ($school->subscription_code) {
                    $q->orWhere('school_subscription_code', $school->subscription_code);
                }
            });
            $studentsQuery->where(function ($q) use ($school) {
                $q->where('school_id', $school->id);
                if ($school->subscription_code) {
                    $q->orWhere('school_subscription_code', $school->subscription_code);
                }
            });
        } elseif ($appRole === 'company' && $company) {
            $applicationsQuery->where('company_id', $company->id);
            $contractsQuery->where('company_user_id', $company->user_id);
            $ojtQuery->whereHas('application', fn ($q) => $q->where('company_id', $company->id));
            $assessmentQuery->whereHas('application', fn ($q) => $q->where('company_id', $company->id));
            $studentsQuery->whereHas('applications', fn ($q) => $q->where('company_id', $company->id));
        } elseif ($appRole !== 'admin') {
            return response()->json(['data' => $this->emptyKpi()]);
        }

        $totalApplications = (clone $applicationsQuery)->count();
        $acceptedApplications = (clone $applicationsQuery)->where('status', 'accepted')->count();
        $applicationsByStatus = (clone $applicationsQuery)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $agreementsByStatus = (clone $contractsQuery)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $studentCount = (clone $studentsQuery)->count();
        $placementRate = $studentCount > 0
            ? round(($acceptedApplications / $studentCount) * 100, 1)
            : ($totalApplications > 0 ? round(($acceptedApplications / $totalApplications) * 100, 1) : 0);

        $approvedPerStudent = (clone $ojtQuery)
            ->select('student_id', DB::raw('SUM(hours) as total_hours'))
            ->groupBy('student_id')
            ->get();
        $avgApprovedOjtHours = $approvedPerStudent->isEmpty()
            ? 0.0
            : round((float) $approvedPerStudent->avg('total_hours'), 2);

        $avgAssessmentScore = round((float) ((clone $assessmentQuery)->whereNotNull('overall_score')->avg('overall_score') ?? 0), 2);

        return response()->json([
            'data' => [
                'placementRate' => $placementRate,
                'applicationCountsByStatus' => $applicationsByStatus,
                'totalApplications' => $totalApplications,
                'acceptedApplications' => $acceptedApplications,
                'agreementCountsByStatus' => $agreementsByStatus,
                'totalAgreements' => array_sum(array_map('intval', $agreementsByStatus)),
                'avgApprovedOjtHours' => $avgApprovedOjtHours,
                'avgAssessmentScore' => $avgAssessmentScore,
                'studentCount' => $studentCount,
                'scopedRole' => $appRole,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyKpi(): array
    {
        return [
            'placementRate' => 0,
            'applicationCountsByStatus' => [],
            'totalApplications' => 0,
            'acceptedApplications' => 0,
            'agreementCountsByStatus' => [],
            'totalAgreements' => 0,
            'avgApprovedOjtHours' => 0,
            'avgAssessmentScore' => 0,
            'studentCount' => 0,
            'scopedRole' => null,
        ];
    }
}
