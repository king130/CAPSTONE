<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Internship;
use App\Models\School;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class InternshipController extends Controller
{
    public function index(Request $request)
    {
        $query = Internship::query()->with(['company.user', 'school.user'])->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('company_id')) {
            $query->where('company_id', $request->integer('company_id'));
        }

        if ($request->filled('company_user_id')) {
            $cid = Company::query()->where('user_id', $request->integer('company_user_id'))->value('id');
            if ($cid) {
                $query->where('company_id', $cid);
            }
        }

        if ($request->filled('school_id')) {
            $query->where('school_id', $request->integer('school_id'));
        }

        if ($request->filled('school_user_id')) {
            $sid = School::query()->where('user_id', $request->integer('school_user_id'))->value('id');
            if ($sid) {
                $query->where('school_id', $sid);
            }
        }

        if ($request->filled('host_type')) {
            $query->where('host_type', $request->string('host_type'));
        }

        return response()->json(['data' => $query->get()->map(fn ($i) => $this->serialize($i))]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $company = $user->organizationCompany() ?? $user->company;
        $school = $user->organizationSchool() ?? $user->school;
        $appRole = $user->effectiveAppRole();

        if ($appRole !== 'company' && $appRole !== 'school') {
            return response()->json(['message' => 'Only company or school accounts can post internships.'], Response::HTTP_FORBIDDEN);
        }

        if ($appRole === 'company' && ! $company) {
            return response()->json(['message' => 'Company profile not found.'], Response::HTTP_FORBIDDEN);
        }

        if ($appRole === 'school' && ! $school) {
            return response()->json(['message' => 'School profile not found.'], Response::HTTP_FORBIDDEN);
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:255'],
            'duration' => ['nullable', 'string', 'max:255'],
            'slots_available' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'in:active,draft,closed'],
            'requirements' => ['nullable', 'array'],
            'eligible_courses' => ['nullable', 'array'],
            'allowance' => ['nullable', 'string', 'max:255'],
            'contact_info' => ['nullable', 'string', 'max:500'],
        ]);

        $internship = Internship::query()->create([
            'company_id' => $appRole === 'company' ? $company->id : null,
            'school_id' => $appRole === 'school' ? $school->id : null,
            'company_name' => $appRole === 'company' ? $company->company_name : null,
            'host_type' => $appRole,
            'host_name' => $appRole === 'school' ? $school->school_name : $company->company_name,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'location' => $data['location'] ?? null,
            'type' => $data['type'] ?? null,
            'duration' => $data['duration'] ?? null,
            'slots_available' => $data['slots_available'] ?? 1,
            'status' => $data['status'] ?? 'active',
            'requirements' => $data['requirements'] ?? null,
            'eligible_courses' => $data['eligible_courses'] ?? null,
            'allowance' => $data['allowance'] ?? null,
            'contact_info' => $data['contact_info'] ?? null,
            'approval_status' => 'approved',
        ]);

        $internship->load(['company.user', 'school.user']);

        return response()->json(['data' => $this->serialize($internship)], Response::HTTP_CREATED);
    }

    public function show(Internship $internship)
    {
        $internship->load(['company.user', 'school.user']);

        return response()->json(['data' => $this->serialize($internship)]);
    }

    public function update(Request $request, Internship $internship)
    {
        $user = $request->user();
        $company = $user->organizationCompany() ?? $user->company;
        $school = $user->organizationSchool() ?? $user->school;
        $appRole = $user->effectiveAppRole();

        $ownsCompanyInternship = $appRole === 'company' && $company && $internship->company_id === $company->id;
        $ownsSchoolInternship = $appRole === 'school' && $school && $internship->school_id === $school->id;

        if (! $ownsCompanyInternship && ! $ownsSchoolInternship) {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:255'],
            'duration' => ['nullable', 'string', 'max:255'],
            'slots_available' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'in:active,draft,closed'],
            'requirements' => ['nullable', 'array'],
            'eligible_courses' => ['nullable', 'array'],
            'allowance' => ['nullable', 'string', 'max:255'],
            'contact_info' => ['nullable', 'string', 'max:500'],
        ]);

        $internship->update(array_filter($data, fn ($v) => $v !== null));

        $internship->load(['company.user', 'school.user']);

        return response()->json(['data' => $this->serialize($internship)]);
    }

    public function destroy(Request $request, Internship $internship)
    {
        $user = $request->user();
        $company = $user->organizationCompany() ?? $user->company;
        $school = $user->organizationSchool() ?? $user->school;
        $appRole = $user->effectiveAppRole();

        $ownsCompanyInternship = $appRole === 'company' && $company && $internship->company_id === $company->id;
        $ownsSchoolInternship = $appRole === 'school' && $school && $internship->school_id === $school->id;

        if (! $ownsCompanyInternship && ! $ownsSchoolInternship) {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $internship->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Internship $i): array
    {
        $companyUserId = $i->company?->user_id ? (string) $i->company->user_id : '';
        $schoolUserId = $i->school?->user_id ? (string) $i->school->user_id : '';
        $hostType = $i->host_type ?: ($i->school_id ? 'school' : 'company');
        $hostName = $i->host_name ?: ($hostType === 'school'
            ? ($i->school?->school_name ?? '')
            : ($i->company_name ?? $i->company?->company_name ?? ''));
        $hostUserId = $hostType === 'school' ? $schoolUserId : $companyUserId;

        return [
            'id' => (string) $i->id,
            'title' => $i->title,
            'description' => $i->description,
            'companyId' => $companyUserId,
            'companyName' => $hostName,
            'schoolId' => $schoolUserId,
            'schoolName' => $i->school?->school_name ?? '',
            'hostType' => $hostType,
            'hostId' => $hostUserId,
            'hostName' => $hostName,
            'location' => $i->location ?? '',
            'type' => $i->type ?? '',
            'duration' => $i->duration ?? '',
            'slotsAvailable' => $i->slots_available,
            'eligibleCourses' => $i->eligible_courses ?? [],
            'requirements' => $i->requirements ?? [],
            'allowance' => $i->allowance,
            'contactInfo' => $i->contact_info,
            'approvalStatus' => $i->approval_status,
            'approvalNotes' => $i->approval_notes,
            'status' => $i->status,
            'createdAt' => $i->created_at?->toIso8601String(),
            'updatedAt' => $i->updated_at?->toIso8601String(),
        ];
    }
}
