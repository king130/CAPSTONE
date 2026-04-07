<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Support\SchoolTenantPermissions;
use App\Models\Contract;
use App\Models\Internship;
use App\Models\School;
use App\Services\SubscriptionPlanService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class InternshipController extends Controller
{
    /**
     * @return array<int, string|array<int, string>>
     */
    private function internshipRelations(): array
    {
        return [
            'company:id,user_id,company_name',
            'company.user:id,profile',
            'school:id,user_id,institution_name',
        ];
    }

    public function __construct(private readonly SubscriptionPlanService $subscriptionPlans)
    {
    }

    public function index(Request $request)
    {
        $query = Internship::query()
            ->select([
                'id',
                'company_id',
                'school_id',
                'company_name',
                'industry',
                'host_type',
                'host_name',
                'title',
                'description',
                'location',
                'type',
                'duration',
                'slots_available',
                'status',
                'requirements',
                'eligible_courses',
                'allowance',
                'start_date',
                'end_date',
                'schedule',
                'tasks',
                'required_skills',
                'intern_gains',
                'required_documents',
                'application_instructions',
                'contact_info',
                'approval_status',
                'approval_notes',
                'created_at',
                'updated_at',
            ])
            ->with($this->internshipRelations())
            ->orderByDesc('created_at');

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

    public function eligibleForStudent(Request $request)
    {
        $user = $request->user();
        if ($user?->effectiveAppRole() !== 'student' || ! $user->student) {
            return response()->json(['data' => []], Response::HTTP_OK);
        }

        $school = $this->resolveStudentSchool($user->student);
        $schoolUserId = $school?->user_id;

        $activeCompanyUserIds = $schoolUserId
            ? Contract::query()
                ->where('school_user_id', $schoolUserId)
                ->where('status', 'active')
                ->pluck('company_user_id')
                ->map(fn ($value) => (int) $value)
                ->filter()
                ->values()
            : collect();

        $query = Internship::query()
            ->select([
                'id',
                'company_id',
                'school_id',
                'company_name',
                'industry',
                'host_type',
                'host_name',
                'title',
                'description',
                'location',
                'type',
                'duration',
                'slots_available',
                'status',
                'requirements',
                'eligible_courses',
                'allowance',
                'start_date',
                'end_date',
                'schedule',
                'tasks',
                'required_skills',
                'intern_gains',
                'required_documents',
                'application_instructions',
                'contact_info',
                'approval_status',
                'approval_notes',
                'created_at',
                'updated_at',
            ])
            ->with($this->internshipRelations())
            ->where('status', 'active')
            ->where(function ($builder) use ($activeCompanyUserIds) {
                $builder->where(function ($schoolBuilder) {
                    $schoolBuilder
                        ->where('host_type', 'school')
                        ->orWhere(function ($fallbackSchoolBuilder) {
                            $fallbackSchoolBuilder
                                ->whereNull('host_type')
                                ->whereNotNull('school_id');
                        });
                });

                if ($activeCompanyUserIds->isNotEmpty()) {
                    $companyIds = Company::query()
                        ->whereIn('user_id', $activeCompanyUserIds)
                        ->pluck('id');

                    $builder->orWhere(function ($companyBuilder) use ($companyIds) {
                        $companyBuilder->where(function ($companyHostBuilder) {
                            $companyHostBuilder
                                ->where('host_type', 'company')
                                ->orWhere(function ($fallbackCompanyBuilder) {
                                    $fallbackCompanyBuilder
                                        ->whereNull('host_type')
                                        ->whereNotNull('company_id');
                                });
                        });
                        if ($companyIds->isNotEmpty()) {
                            $companyBuilder->whereIn('company_id', $companyIds);
                        } else {
                            $companyBuilder->whereRaw('1 = 0');
                        }
                    });
                }
            })
            ->orderByDesc('created_at');

        return response()->json([
            'data' => $query->get()->map(fn ($internship) => $this->serialize($internship)),
        ], Response::HTTP_OK);
    }

    private function resolveStudentSchool(\App\Models\Student $student): ?School
    {
        if ($student->school_id) {
            $school = School::query()->find($student->school_id);
            if ($school) {
                return $school;
            }
        }

        if ($student->organization_id) {
            $school = School::query()->where('organization_id', $student->organization_id)->first();
            if ($school) {
                return $school;
            }
        }

        if ($student->school_subscription_code) {
            $school = School::query()->where('subscription_code', $student->school_subscription_code)->first();
            if ($school) {
                return $school;
            }
        }

        if ($student->school_name) {
            return School::query()->where('institution_name', $student->school_name)->first();
        }

        return null;
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

        if ($appRole === 'school' && ! SchoolTenantPermissions::userMayCoordinateSchoolTenant($user)) {
            return response()->json(['message' => 'You do not have permission to post or edit school opportunities.'], Response::HTTP_FORBIDDEN);
        }

        $organization = $user->activeOrganization();
        if ($appRole === 'company' && $organization && $this->subscriptionPlans->wouldExceedAfterIncrement($organization, 'company.internships')) {
            $overage = $this->subscriptionPlans->getOverageForResource($organization, 'company.internships') ?? [
                'key' => 'company.internships',
                'message' => 'Your organization has reached the internship posting limit for the current subscription plan.',
            ];

            return response()->json([
                'message' => $overage['message'],
                'overage' => $overage,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:255'],
            'industry' => ['nullable', 'string', 'max:255'],
            'duration' => ['nullable', 'string', 'max:255'],
            'slots_available' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'in:active,draft,closed'],
            'requirements' => ['nullable', 'array'],
            'eligible_courses' => ['nullable', 'array'],
            'allowance' => ['nullable', 'string', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'schedule' => ['nullable', 'string', 'max:255'],
            'tasks' => ['nullable', 'string'],
            'required_skills' => ['nullable', 'array'],
            'intern_gains' => ['nullable', 'string'],
            'required_documents' => ['nullable', 'array'],
            'application_instructions' => ['nullable', 'string'],
            'contact_info' => ['nullable', 'string', 'max:500'],
        ]);

        $internship = Internship::query()->create([
            'company_id' => $appRole === 'company' ? $company->id : null,
            'school_id' => $appRole === 'school' ? $school->id : null,
            'company_name' => $appRole === 'company' ? $company->company_name : null,
            'industry' => $data['industry'] ?? null,
            'host_type' => $appRole,
            'host_name' => $appRole === 'school' ? $school->institution_name : $company->company_name,
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
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'schedule' => $data['schedule'] ?? null,
            'tasks' => $data['tasks'] ?? null,
            'required_skills' => $data['required_skills'] ?? null,
            'intern_gains' => $data['intern_gains'] ?? null,
            'required_documents' => $data['required_documents'] ?? null,
            'application_instructions' => $data['application_instructions'] ?? null,
            'contact_info' => $data['contact_info'] ?? null,
            'approval_status' => 'approved',
        ]);

        $internship->load($this->internshipRelations());
        if ($organization) {
            $this->subscriptionPlans->syncOrganizationCompliance($organization);
        }

        return response()->json(['data' => $this->serialize($internship)], Response::HTTP_CREATED);
    }

    public function show(Internship $internship)
    {
        $internship->load($this->internshipRelations());

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

        if ($ownsSchoolInternship && ! SchoolTenantPermissions::userMayCoordinateSchoolTenant($user)) {
            return response()->json(['message' => 'You do not have permission to edit school opportunities.'], Response::HTTP_FORBIDDEN);
        }

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:255'],
            'industry' => ['nullable', 'string', 'max:255'],
            'duration' => ['nullable', 'string', 'max:255'],
            'slots_available' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'in:active,draft,closed'],
            'requirements' => ['nullable', 'array'],
            'eligible_courses' => ['nullable', 'array'],
            'allowance' => ['nullable', 'string', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'schedule' => ['nullable', 'string', 'max:255'],
            'tasks' => ['nullable', 'string'],
            'required_skills' => ['nullable', 'array'],
            'intern_gains' => ['nullable', 'string'],
            'required_documents' => ['nullable', 'array'],
            'application_instructions' => ['nullable', 'string'],
            'contact_info' => ['nullable', 'string', 'max:500'],
        ]);

        $internship->update(array_filter($data, fn ($v) => $v !== null));

        $internship->load($this->internshipRelations());

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

        if ($ownsSchoolInternship && ! SchoolTenantPermissions::userMayCoordinateSchoolTenant($user)) {
            return response()->json(['message' => 'You do not have permission to remove school opportunities.'], Response::HTTP_FORBIDDEN);
        }

        $internship->delete();
        $organization = $user->activeOrganization();
        if ($organization) {
            $this->subscriptionPlans->syncOrganizationCompliance($organization);
        }

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
            ? ($i->school?->institution_name ?? '')
            : ($i->company_name ?? $i->company?->company_name ?? ''));
        $hostUserId = $hostType === 'school' ? $schoolUserId : $companyUserId;

        return [
            'id' => (string) $i->id,
            'title' => $i->title,
            'description' => $i->description,
            'companyId' => $companyUserId,
            'companyName' => $hostName,
            'schoolId' => $schoolUserId,
            'schoolName' => $i->school?->institution_name ?? '',
            'hostType' => $hostType,
            'hostId' => $hostUserId,
            'hostName' => $hostName,
            'industry' => $i->industry ?? '',
            'location' => $i->location ?? '',
            'type' => $i->type ?? '',
            'duration' => $i->duration ?? '',
            'slotsAvailable' => $i->slots_available,
            'eligibleCourses' => $this->resolveEligibleCourses($i, $hostType),
            'requirements' => $i->requirements ?? [],
            'allowance' => $i->allowance,
            'startDate' => $i->start_date?->toDateString(),
            'endDate' => $i->end_date?->toDateString(),
            'schedule' => $i->schedule,
            'tasks' => $i->tasks,
            'requiredSkills' => $i->required_skills ?? [],
            'internGains' => $i->intern_gains,
            'requiredDocuments' => $i->required_documents ?? [],
            'applicationInstructions' => $i->application_instructions,
            'contactInfo' => $i->contact_info,
            'approvalStatus' => $i->approval_status,
            'approvalNotes' => $i->approval_notes,
            'status' => $i->status,
            'createdAt' => $i->created_at?->toIso8601String(),
            'updatedAt' => $i->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function resolveEligibleCourses(Internship $internship, string $hostType): array
    {
        $storedCourses = collect($internship->eligible_courses ?? []);

        if ($hostType !== 'company') {
            return $storedCourses
                ->map(fn ($course) => is_string($course) ? trim($course) : '')
                ->filter()
                ->values()
                ->all();
        }

        $normalizedStoredCourses = $storedCourses
            ->map(fn ($course) => is_string($course) ? trim($course) : '')
            ->filter()
            ->values()
            ->all();

        if (! empty($normalizedStoredCourses)) {
            return $normalizedStoredCourses;
        }

        return collect($internship->company?->user?->profile['courses'] ?? [])
            ->map(fn ($course) => is_string($course) ? trim($course) : '')
            ->filter()
            ->unique(fn (string $course) => strtolower($course))
            ->values()
            ->all();
    }
}
