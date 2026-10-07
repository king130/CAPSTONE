<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\ApplicationAssessment;
use App\Models\OjtLog;
use App\Models\Student;
use App\Services\PermissionGate;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class StudentProgressController extends Controller
{
    public function __construct(private readonly PermissionGate $permissions)
    {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $appRole = $user->effectiveAppRole();

        if ($appRole !== 'admin' && ! $this->permissions->userCanAny($user, ['org.view_ojt_progress', 'org.view_students'])) {
            return response()->json(['message' => 'Missing permission to view student progress.'], Response::HTTP_FORBIDDEN);
        }

        $school = $user->organizationSchool() ?? $user->school;
        $company = $user->organizationCompany() ?? $user->company;

        $studentsQuery = Student::query()->with(['user:id,name,email', 'school:id,institution_name']);

        if ($appRole === 'school' && $school) {
            $studentsQuery->where(function ($q) use ($school) {
                $q->where('school_id', $school->id);
                if ($school->subscription_code) {
                    $q->orWhere('school_subscription_code', $school->subscription_code);
                }
            });
        } elseif ($appRole === 'company' && $company) {
            $studentsQuery->whereHas('applications', fn ($q) => $q->where('company_id', $company->id));
        } elseif ($appRole !== 'admin') {
            return response()->json(['data' => []]);
        }

        $requiredHours = OjtLogController::requiredHoursForOrg($user->activeOrganization());

        $items = $studentsQuery->orderBy('id')->get()->map(function (Student $student) use ($requiredHours, $company, $appRole) {
            $applicationsQuery = Application::query()->where('student_id', $student->id);
            if ($appRole === 'company' && $company) {
                $applicationsQuery->where('company_id', $company->id);
            }

            $latestApplication = (clone $applicationsQuery)->orderByDesc('updated_at')->first();
            $acceptedApplication = (clone $applicationsQuery)->where('status', 'accepted')->orderByDesc('updated_at')->first();

            $approvedHours = (float) OjtLog::query()
                ->where('student_id', $student->id)
                ->where('status', 'approved')
                ->sum('hours');

            $pendingHours = (float) OjtLog::query()
                ->where('student_id', $student->id)
                ->where('status', 'pending')
                ->sum('hours');

            $latestAssessment = null;
            if ($latestApplication) {
                $latestAssessment = ApplicationAssessment::query()
                    ->where('application_id', $latestApplication->id)
                    ->orderByDesc('created_at')
                    ->first();
            }

            return [
                'studentId' => (string) $student->id,
                'userId' => $student->user_id ? (string) $student->user_id : null,
                'name' => $student->user?->name ?? $student->user?->email ?? 'Student',
                'email' => $student->user?->email,
                'course' => $student->course,
                'schoolName' => $student->school_name ?? $student->school?->institution_name,
                'applicationStatus' => $latestApplication?->status,
                'internshipTitle' => $acceptedApplication?->internship_title ?? $latestApplication?->internship_title,
                'approvedOjtHours' => round($approvedHours, 2),
                'pendingOjtHours' => round($pendingHours, 2),
                'requiredOjtHours' => $requiredHours,
                'ojtPercentComplete' => $requiredHours > 0 ? min(100, round(($approvedHours / $requiredHours) * 100, 1)) : 0,
                'latestAssessment' => $latestAssessment ? [
                    'id' => (string) $latestAssessment->id,
                    'stage' => $latestAssessment->stage,
                    'overallScore' => $latestAssessment->overall_score !== null ? (float) $latestAssessment->overall_score : null,
                    'comments' => $latestAssessment->comments,
                    'createdAt' => $latestAssessment->created_at?->toIso8601String(),
                ] : null,
            ];
        })->values();

        return response()->json(['data' => $items]);
    }
}
