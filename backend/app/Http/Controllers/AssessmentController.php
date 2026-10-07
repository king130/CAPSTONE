<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\ApplicationAssessment;
use App\Services\PermissionGate;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AssessmentController extends Controller
{
    public function __construct(private readonly PermissionGate $permissions)
    {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $appRole = $user->effectiveAppRole();

        if (
            $appRole !== 'admin'
            && $appRole !== 'student'
            && ! $this->permissions->userCanAny($user, ['org.manage_assessments', 'org.approve_applications', 'org.view_applications'])
        ) {
            return response()->json(['message' => 'Missing permission to view assessments.'], Response::HTTP_FORBIDDEN);
        }

        $query = ApplicationAssessment::query()
            ->with([
                'application:id,internship_id,student_id,company_id,status,internship_title,student_name',
                'application.student:id,user_id,school_id,school_name',
                'application.company:id,company_name',
                'assessedBy:id,name,email',
            ])
            ->orderByDesc('created_at');

        if ($request->filled('application_id')) {
            $query->where('application_id', $request->integer('application_id'));
        }

        $company = $user->organizationCompany() ?? $user->company;
        $school = $user->organizationSchool() ?? $user->school;

        if ($appRole === 'student' && $user->student) {
            $query->whereHas('application', fn ($q) => $q->where('student_id', $user->student->id));
        } elseif ($appRole === 'company' && $company) {
            $query->whereHas('application', fn ($q) => $q->where('company_id', $company->id));
        } elseif ($appRole === 'school' && $school) {
            $query->whereHas('application.student', function ($studentQuery) use ($school) {
                $studentQuery->where('school_id', $school->id);
                if ($school->subscription_code) {
                    $studentQuery->orWhere('school_subscription_code', $school->subscription_code);
                }
            });
        } elseif ($appRole !== 'admin') {
            return response()->json(['data' => []]);
        }

        return response()->json([
            'data' => $query->get()->map(fn (ApplicationAssessment $assessment) => $this->serialize($assessment)),
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $appRole = $user->effectiveAppRole();

        if ($appRole !== 'admin' && ! $this->permissions->userCanAny($user, ['org.manage_assessments', 'org.approve_applications'])) {
            return response()->json(['message' => 'Missing permission to manage assessments.'], Response::HTTP_FORBIDDEN);
        }

        $data = $request->validate([
            'application_id' => ['required', 'integer', 'exists:applications,id'],
            'stage' => ['nullable', 'string', 'max:50'],
            'rubric_scores' => ['nullable', 'array'],
            'overall_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'comments' => ['nullable', 'string'],
        ]);

        $application = Application::query()->with(['student', 'company'])->findOrFail($data['application_id']);

        if (! $this->canAccessApplication($user, $application)) {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $assessment = ApplicationAssessment::query()->create([
            'application_id' => $application->id,
            'assessed_by_user_id' => $user->id,
            'stage' => $data['stage'] ?? 'pre_screen',
            'rubric_scores' => $data['rubric_scores'] ?? null,
            'overall_score' => $data['overall_score'] ?? null,
            'comments' => $data['comments'] ?? null,
        ]);

        $assessment->load([
            'application:id,internship_id,student_id,company_id,status,internship_title,student_name',
            'application.student:id,user_id,school_id,school_name',
            'application.company:id,company_name',
            'assessedBy:id,name,email',
        ]);

        return response()->json(['data' => $this->serialize($assessment)], Response::HTTP_CREATED);
    }

    private function canAccessApplication($user, Application $application): bool
    {
        $appRole = $user->effectiveAppRole();
        if ($appRole === 'admin') {
            return true;
        }

        $company = $user->organizationCompany() ?? $user->company;
        if ($appRole === 'company' && $company) {
            return (int) $application->company_id === (int) $company->id;
        }

        $school = $user->organizationSchool() ?? $user->school;
        if ($appRole === 'school' && $school && $application->student) {
            if ((int) $application->student->school_id === (int) $school->id) {
                return true;
            }
            if ($school->subscription_code && $application->student->school_subscription_code === $school->subscription_code) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(ApplicationAssessment $assessment): array
    {
        return [
            'id' => (string) $assessment->id,
            'applicationId' => (string) $assessment->application_id,
            'assessedByUserId' => (string) $assessment->assessed_by_user_id,
            'assessedByName' => $assessment->assessedBy?->name ?? $assessment->assessedBy?->email,
            'stage' => $assessment->stage,
            'rubricScores' => $assessment->rubric_scores ?? [],
            'overallScore' => $assessment->overall_score !== null ? (float) $assessment->overall_score : null,
            'comments' => $assessment->comments,
            'application' => $assessment->application ? [
                'id' => (string) $assessment->application->id,
                'internshipTitle' => $assessment->application->internship_title,
                'studentName' => $assessment->application->student_name,
                'status' => $assessment->application->status,
                'companyName' => $assessment->application->company?->company_name,
            ] : null,
            'createdAt' => $assessment->created_at?->toIso8601String(),
            'updatedAt' => $assessment->updated_at?->toIso8601String(),
        ];
    }
}
