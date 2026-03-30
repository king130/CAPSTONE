<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Internship;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ApplicationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $appRole = $user->effectiveAppRole();
        $query = Application::query()->with(['student.user', 'internship.company']);

        $company = $user->organizationCompany() ?? $user->company;

        if ($appRole === 'student' && $user->student) {
            $query->where('student_id', $user->student->id);
        } elseif ($appRole === 'company' && $company) {
            $query->where('company_id', $company->id);
        } elseif ($appRole === 'admin') {
            // list all
        } else {
            return response()->json(['data' => []]);
        }

        $items = $query->orderByDesc('created_at')->get()->map(fn ($a) => $this->serialize($a));

        return response()->json(['data' => $items]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        if ($user->effectiveAppRole() !== 'student' || ! $user->student) {
            return response()->json(['message' => 'Only students can apply.'], Response::HTTP_FORBIDDEN);
        }

        $data = $request->validate([
            'internship_id' => ['required', 'integer', 'exists:internships,id'],
            'resume_url' => ['nullable', 'string', 'max:2048'],
            'documents' => ['nullable', 'array'],
            'cover_letter' => ['nullable', 'string'],
        ]);

        $internship = Internship::query()->with('company')->findOrFail($data['internship_id']);

        $student = $user->student;
        $companyId = $internship->company_id;

        $application = Application::query()->create([
            'internship_id' => $internship->id,
            'student_id' => $student->id,
            'company_id' => $companyId,
            'status' => 'submitted',
            'cover_letter' => $data['cover_letter'] ?? null,
            'resume_url' => $data['resume_url'] ?? null,
            'documents' => $data['documents'] ?? null,
            'documents_pending' => false,
            'internship_title' => $internship->title,
            'student_name' => $user->name,
            'student_email' => $user->email,
            'student_course' => $student->course,
        ]);

        return response()->json(['data' => $this->serialize($application)], Response::HTTP_CREATED);
    }

    public function updateStatus(Request $request, Application $application)
    {
        $user = $request->user();
        $appRole = $user->effectiveAppRole();
        $internship = $application->internship;

        $company = $user->organizationCompany() ?? $user->company;

        $canCompany = $appRole === 'company'
            && $company
            && $internship
            && $internship->company_id === $company->id;

        $canStudent = $appRole === 'student'
            && $user->student
            && $application->student_id === $user->student->id;

        if (! $canCompany && ! $canStudent && $appRole !== 'admin') {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $data = $request->validate([
            'status' => ['required', 'string', 'max:64'],
        ]);

        $application->update(['status' => $data['status']]);

        return response()->json(['data' => $this->serialize($application->fresh())]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Application $a): array
    {
        $a->loadMissing('student', 'internship.company');

        $companyUserId = '';
        if ($a->internship?->company?->user_id) {
            $companyUserId = (string) $a->internship->company->user_id;
        }

        return [
            'id' => (string) $a->id,
            'internshipId' => (string) $a->internship_id,
            'studentId' => $a->student ? (string) $a->student->user_id : '',
            'companyId' => $companyUserId,
            'internshipTitle' => $a->internship_title,
            'studentName' => $a->student_name,
            'studentEmail' => $a->student_email,
            'studentCourse' => $a->student_course,
            'status' => $a->status,
            'resume' => $a->resume_url,
            'documents' => $a->documents,
            'documentsPending' => $a->documents_pending,
            'createdAt' => $a->created_at?->toIso8601String(),
            'updatedAt' => $a->updated_at?->toIso8601String(),
        ];
    }
}
