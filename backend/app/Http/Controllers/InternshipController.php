<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Contract;
use App\Models\Internship;
use App\Models\OrganizationMedia;
use App\Models\SavedInternship;
use App\Models\School;
use App\Models\Student;
use App\Services\InternshipEligibilityService;
use App\Services\PermissionGate;
use App\Services\SubscriptionPlanService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

class InternshipController extends Controller
{
    /**
     * @return array<int, string|\Closure>
     */
    private function internshipRelations(): array
    {
        return [
            'company:id,user_id,company_name,organization_id,verification_status,website,company_address,industry_type',
            'company.user:id,profile',
            'company.organization:id,name,type,is_active,tagline,description,address,city,website,industry,perks',
            'company.organization.media',
            'school:id,user_id,institution_name,organization_id,verification_status,school_address',
            'school.organization:id,name,type,is_active,tagline,description,address,city,website,industry,perks',
            'school.organization.media',
        ];
    }

    public function __construct(
        private readonly SubscriptionPlanService $subscriptionPlans,
        private readonly PermissionGate $permissions,
        private readonly InternshipEligibilityService $internshipEligibility,
    ) {
    }

    public function index(Request $request)
    {
        $query = Internship::query()
            ->with($this->internshipRelations())
            ->withCount([
                'applications as accepted_applications_count' => fn (Builder $builder) => $builder->where('status', 'accepted'),
            ]);

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

        $this->applyCatalogFilters($query, $request);
        $this->applyCatalogSort($query, $request);

        $viewerContext = $this->viewerPartnershipContext($request);

        if (! $request->filled('page')) {
            $items = $query->get()->map(fn (Internship $internship) => $this->serialize($internship, $viewerContext));

            return response()->json(['data' => $items]);
        }

        $perPage = max(1, min(100, (int) $request->input('per_page', 15)));
        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => collect($paginator->items())->map(
                fn (Internship $internship) => $this->serialize($internship, $viewerContext)
            )->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
        ]);
    }

    public function eligibleForStudent(Request $request)
    {
        $user = $request->user();
        if ($user?->effectiveAppRole() !== 'student' || ! $user->student) {
            return response()->json(['data' => []], Response::HTTP_OK);
        }

        $query = Internship::query()
            ->with($this->internshipRelations())
            ->withCount([
                'applications as accepted_applications_count' => fn (Builder $builder) => $builder->where('status', 'accepted'),
            ])
            ->orderByDesc('created_at');

        $constrained = $this->internshipEligibility->applyEligibleListingConstraints($query, $user->student);
        if ($constrained === null) {
            return response()->json(['data' => []], Response::HTTP_OK);
        }

        $viewerContext = $this->viewerPartnershipContext($request);

        return response()->json([
            'data' => $constrained->get()->map(fn (Internship $internship) => $this->serialize($internship, $viewerContext)),
        ], Response::HTTP_OK);
    }

    private function resolveStudentSchool(\App\Models\Student $student): ?School
    {
        return $this->internshipEligibility->resolveStudentSchool($student);
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

        if (! $this->permissions->userCan($user, 'org.manage_internships')) {
            return response()->json(['message' => 'Missing permission to manage internships.'], Response::HTTP_FORBIDDEN);
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
            'city' => ['nullable', 'string', 'max:255'],
            'work_setup' => ['nullable', 'string', 'max:32'],
            'type' => ['nullable', 'string', 'max:255'],
            'industry' => ['nullable', 'string', 'max:255'],
            'duration' => ['nullable', 'string', 'max:255'],
            'slots_available' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'in:active,draft,closed'],
            'requirements' => ['nullable', 'array'],
            'eligible_courses' => ['nullable', 'array'],
            'allowance' => ['nullable', 'string', 'max:255'],
            'perks' => ['nullable', 'array'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'application_deadline' => ['nullable', 'date'],
            'schedule' => ['nullable', 'string', 'max:255'],
            'schedule_type' => ['nullable', 'string', 'max:50'],
            'weekly_hours' => ['nullable', 'numeric', 'min:0', 'max:168'],
            'schedule_days' => ['nullable', 'array'],
            'schedule_days.*' => ['string', 'max:20'],
            'time_in' => ['nullable', 'string', 'max:20'],
            'time_out' => ['nullable', 'string', 'max:20'],
            'timezone' => ['nullable', 'string', 'max:100'],
            'is_flexible' => ['nullable', 'boolean'],
            'tasks' => ['nullable', 'string'],
            'required_skills' => ['nullable', 'array'],
            'intern_gains' => ['nullable', 'string'],
            'required_documents' => ['nullable', 'array'],
            'application_instructions' => ['nullable', 'string'],
            'contact_info' => ['nullable', 'string', 'max:500'],
        ]);

        $scheduleFields = $this->extractScheduleFields($data);
        $workSetup = $this->normalizeWorkSetup($data['work_setup'] ?? $data['type'] ?? null);

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
            'city' => $data['city'] ?? $this->extractCityFromLocation($data['location'] ?? null),
            'work_setup' => $workSetup,
            'type' => $data['type'] ?? $workSetup,
            'duration' => $data['duration'] ?? null,
            'slots_available' => $data['slots_available'] ?? 1,
            'status' => $data['status'] ?? 'active',
            'requirements' => $data['requirements'] ?? null,
            'eligible_courses' => $data['eligible_courses'] ?? null,
            'allowance' => $data['allowance'] ?? null,
            'perks' => $data['perks'] ?? null,
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'application_deadline' => $data['application_deadline'] ?? null,
            'schedule' => $scheduleFields['schedule'],
            'schedule_type' => $scheduleFields['schedule_type'],
            'weekly_hours' => $scheduleFields['weekly_hours'],
            'schedule_days' => $scheduleFields['schedule_days'],
            'time_in' => $scheduleFields['time_in'],
            'time_out' => $scheduleFields['time_out'],
            'timezone' => $scheduleFields['timezone'],
            'is_flexible' => $scheduleFields['is_flexible'],
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

    public function show(Request $request, Internship $internship)
    {
        $internship->load($this->internshipRelations());
        $internship->loadCount([
            'applications as accepted_applications_count' => fn (Builder $builder) => $builder->where('status', 'accepted'),
        ]);

        $payload = $this->serialize($internship, $this->viewerPartnershipContext($request));
        $media = $this->resolveOrganizationMediaGallery($internship, $payload['hostType'] ?? 'company');
        $payload['organizationMedia'] = $media;
        $payload['organization_media'] = $media;

        return response()->json(['data' => $payload]);
    }

    /**
     * @return array<int, string|\Closure>
     */
    public function catalogRelations(): array
    {
        return $this->internshipRelations();
    }

    /**
     * @return array{studentSchoolId:?int,partnerCompanyUserIds:Collection<int,int|string>}|null
     */
    public function catalogViewerContext(Request $request): ?array
    {
        return $this->viewerPartnershipContext($request);
    }

    /**
     * @param  array{studentSchoolId:?int,partnerCompanyUserIds:Collection<int,int|string>}|null  $viewerContext
     * @return array<string, mixed>
     */
    public function catalogSerialize(Internship $internship, ?array $viewerContext = null): array
    {
        return $this->serialize($internship, $viewerContext);
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

        if (! $this->permissions->userCan($user, 'org.manage_internships')) {
            return response()->json(['message' => 'Missing permission to manage internships.'], Response::HTTP_FORBIDDEN);
        }

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'work_setup' => ['nullable', 'string', 'max:32'],
            'type' => ['nullable', 'string', 'max:255'],
            'industry' => ['nullable', 'string', 'max:255'],
            'duration' => ['nullable', 'string', 'max:255'],
            'slots_available' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'in:active,draft,closed'],
            'requirements' => ['nullable', 'array'],
            'eligible_courses' => ['nullable', 'array'],
            'allowance' => ['nullable', 'string', 'max:255'],
            'perks' => ['nullable', 'array'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'application_deadline' => ['nullable', 'date'],
            'schedule' => ['nullable', 'string', 'max:255'],
            'schedule_type' => ['nullable', 'string', 'max:50'],
            'weekly_hours' => ['nullable', 'numeric', 'min:0', 'max:168'],
            'schedule_days' => ['nullable', 'array'],
            'schedule_days.*' => ['string', 'max:20'],
            'time_in' => ['nullable', 'string', 'max:20'],
            'time_out' => ['nullable', 'string', 'max:20'],
            'timezone' => ['nullable', 'string', 'max:100'],
            'is_flexible' => ['nullable', 'boolean'],
            'tasks' => ['nullable', 'string'],
            'required_skills' => ['nullable', 'array'],
            'intern_gains' => ['nullable', 'string'],
            'required_documents' => ['nullable', 'array'],
            'application_instructions' => ['nullable', 'string'],
            'contact_info' => ['nullable', 'string', 'max:500'],
        ]);

        $updates = array_filter($data, fn ($v) => $v !== null);
        if (array_key_exists('work_setup', $data)) {
            $updates['work_setup'] = $this->normalizeWorkSetup($data['work_setup']);
        } elseif (array_key_exists('type', $data) && empty($internship->work_setup)) {
            $updates['work_setup'] = $this->normalizeWorkSetup($data['type']);
        }
        if (array_key_exists('location', $data) && ! array_key_exists('city', $data)) {
            $updates['city'] = $this->extractCityFromLocation($data['location']);
        }

        $hasStructuredSchedule = collect(['schedule_type', 'weekly_hours', 'schedule_days', 'time_in', 'time_out', 'timezone', 'is_flexible'])
            ->contains(fn ($key) => array_key_exists($key, $data));

        if ($hasStructuredSchedule || array_key_exists('schedule', $data)) {
            $merged = array_merge([
                'schedule' => $internship->schedule,
                'schedule_type' => $internship->schedule_type,
                'weekly_hours' => $internship->weekly_hours,
                'schedule_days' => $internship->schedule_days,
                'time_in' => $internship->time_in,
                'time_out' => $internship->time_out,
                'timezone' => $internship->timezone,
                'is_flexible' => $internship->is_flexible,
            ], $data);

            $scheduleFields = $this->extractScheduleFields($merged);
            $updates = array_merge($updates, $scheduleFields);
        }

        $internship->update($updates);

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

        if (! $this->permissions->userCan($user, 'org.manage_internships')) {
            return response()->json(['message' => 'Missing permission to manage internships.'], Response::HTTP_FORBIDDEN);
        }

        $internship->delete();
        $organization = $user->activeOrganization();
        if ($organization) {
            $this->subscriptionPlans->syncOrganizationCompliance($organization);
        }

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @param  array{studentSchoolId:?int,partnerCompanyUserIds:Collection<int,int|string>,savedInternshipIds?:array<int,true>}|null  $viewerContext
     * @return array<string, mixed>
     */
    private function serialize(Internship $i, ?array $viewerContext = null): array
    {
        $companyUserId = $i->company?->user_id ? (string) $i->company->user_id : '';
        $schoolUserId = $i->school?->user_id ? (string) $i->school->user_id : '';
        $hostType = $i->host_type ?: ($i->school_id ? 'school' : 'company');
        $hostName = $i->host_name ?: ($hostType === 'school'
            ? ($i->school?->institution_name ?? '')
            : ($i->company_name ?? $i->company?->company_name ?? ''));
        $hostUserId = $hostType === 'school' ? $schoolUserId : $companyUserId;
        $workSetup = $this->normalizeWorkSetup($i->work_setup ?: $i->type);
        $slotsAvailable = max(0, (int) $i->slots_available);
        $acceptedCount = (int) ($i->accepted_applications_count ?? 0);
        $slotsRemaining = max(0, $slotsAvailable - $acceptedCount);
        $city = trim((string) ($i->city ?: $this->extractCityFromLocation($i->location)));
        $coverImage = $this->resolveCoverImageUrl($i, $hostType);
        $verified = $this->resolveVerifiedFlag($i, $hostType);
        $partnered = $this->resolvePartneredWithMySchool($i, $hostType, $viewerContext);
        $saved = $this->resolveSavedFlag($i, $viewerContext);
        $organizationId = $hostType === 'school'
            ? ($i->school?->organization_id ? (string) $i->school->organization_id : null)
            : ($i->company?->organization_id ? (string) $i->company->organization_id : null);

        $payload = [
            'id' => (string) $i->id,
            'title' => $i->title,
            'description' => $i->description,
            'companyId' => $companyUserId,
            'companyName' => $hostName,
            'schoolId' => $schoolUserId,
            'schoolName' => $i->school?->institution_name ?? '',
            'organizationId' => $organizationId,
            'organization_id' => $organizationId,
            'hostType' => $hostType,
            'hostId' => $hostUserId,
            'hostName' => $hostName,
            'industry' => $i->industry ?? '',
            'location' => $i->location ?? '',
            'city' => $city,
            'work_setup' => $workSetup,
            'workSetup' => $workSetup,
            'type' => $i->type ?? $workSetup ?? '',
            'duration' => $i->duration ?? '',
            'slotsAvailable' => $slotsAvailable,
            'slots_available' => $slotsAvailable,
            'slots_remaining' => $slotsRemaining,
            'slotsRemaining' => $slotsRemaining,
            'eligibleCourses' => $this->resolveEligibleCourses($i, $hostType),
            'eligible_courses' => $this->resolveEligibleCourses($i, $hostType),
            'requirements' => $i->requirements ?? [],
            'allowance' => $i->allowance,
            'perks' => $i->perks ?? [],
            'startDate' => $i->start_date?->toDateString(),
            'start_date' => $i->start_date?->toDateString(),
            'endDate' => $i->end_date?->toDateString(),
            'application_deadline' => $i->application_deadline?->toDateString(),
            'schedule' => $i->schedule,
            'scheduleType' => $i->schedule_type,
            'schedule_type' => $i->schedule_type,
            'weeklyHours' => $i->weekly_hours !== null ? (float) $i->weekly_hours : null,
            'scheduleDays' => $i->schedule_days ?? [],
            'timeIn' => $i->time_in,
            'timeOut' => $i->time_out,
            'timezone' => $i->timezone ?? 'Asia/Manila',
            'isFlexible' => (bool) $i->is_flexible,
            'cover_image' => $coverImage,
            'coverImage' => $coverImage,
            'verified' => $verified,
            'partnered_with_my_school' => $partnered,
            'partneredWithMySchool' => $partnered,
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

        if ($saved !== null) {
            $payload['saved'] = $saved;
        }

        return $payload;
    }

    private function applyCatalogFilters(Builder $query, Request $request): void
    {
        if ($request->filled('q')) {
            $q = '%'.strtolower(trim((string) $request->string('q'))).'%';
            $query->where(function (Builder $builder) use ($q) {
                $builder
                    ->whereRaw('LOWER(title) LIKE ?', [$q])
                    ->orWhereRaw('LOWER(COALESCE(description, "")) LIKE ?', [$q])
                    ->orWhereRaw('LOWER(COALESCE(location, "")) LIKE ?', [$q])
                    ->orWhereRaw('LOWER(COALESCE(city, "")) LIKE ?', [$q])
                    ->orWhereRaw('LOWER(COALESCE(company_name, "")) LIKE ?', [$q])
                    ->orWhereRaw('LOWER(COALESCE(host_name, "")) LIKE ?', [$q]);
            });
        }

        $city = trim((string) ($request->input('city') ?: $request->input('location') ?: ''));
        if ($city !== '') {
            $cityLike = '%'.strtolower($city).'%';
            $query->where(function (Builder $builder) use ($cityLike) {
                $builder
                    ->whereRaw('LOWER(COALESCE(city, "")) LIKE ?', [$cityLike])
                    ->orWhereRaw('LOWER(COALESCE(location, "")) LIKE ?', [$cityLike]);
            });
        }

        if ($request->filled('work_setup')) {
            $normalized = $this->normalizeWorkSetup((string) $request->string('work_setup'));
            if ($normalized) {
                $query->where(function (Builder $builder) use ($normalized) {
                    $builder
                        ->where('work_setup', $normalized)
                        ->orWhereRaw('LOWER(COALESCE(type, "")) = ?', [$normalized])
                        ->orWhereRaw('LOWER(REPLACE(COALESCE(type, ""), "-", "")) = ?', [str_replace('-', '', $normalized)]);
                });
            }
        }

        if ($request->filled('course')) {
            $course = trim((string) $request->string('course'));
            $query->where(function (Builder $builder) use ($course) {
                $builder
                    ->whereJsonContains('eligible_courses', $course)
                    ->orWhereNull('eligible_courses')
                    ->orWhere('eligible_courses', '[]')
                    ->orWhereRaw('LOWER(CAST(eligible_courses AS TEXT)) LIKE ?', ['%'.strtolower($course).'%']);
            });
        }

        if ($request->filled('allowance')) {
            $allowance = strtolower(trim((string) $request->string('allowance')));
            if ($allowance === 'with') {
                $query->whereNotNull('allowance')
                    ->whereRaw("TRIM(allowance) != ''")
                    ->whereRaw("LOWER(allowance) NOT LIKE '%unpaid%'")
                    ->whereRaw("LOWER(allowance) NOT IN ('none', 'n/a', '0')");
            } elseif ($allowance === 'without') {
                $query->where(function (Builder $builder) {
                    $builder
                        ->whereNull('allowance')
                        ->orWhereRaw("TRIM(allowance) = ''")
                        ->orWhereRaw("LOWER(allowance) LIKE '%unpaid%'")
                        ->orWhereRaw("LOWER(allowance) IN ('none', 'n/a', '0')");
                });
            } elseif (is_numeric($allowance)) {
                $min = (float) $allowance;
                $query->whereRaw(
                    "CAST(REPLACE(REPLACE(REPLACE(COALESCE(allowance, '0'), ',', ''), '₱', ''), ' ', '') AS REAL) >= ?",
                    [$min]
                );
            }
        }

        if ($request->filled('schedule_type')) {
            $schedule = strtolower(trim((string) $request->string('schedule_type')));
            if ($schedule === 'flexible') {
                $query->where(function (Builder $builder) {
                    $builder
                        ->where('is_flexible', true)
                        ->orWhereRaw("LOWER(COALESCE(schedule_type, '')) LIKE '%flex%'");
                });
            } else {
                $query->whereRaw('LOWER(COALESCE(schedule_type, "")) LIKE ?', ['%'.$schedule.'%']);
            }
        }
    }

    private function applyCatalogSort(Builder $query, Request $request): void
    {
        $sort = strtolower(trim((string) $request->input('sort', 'newest')));

        match ($sort) {
            'slots', 'slots_remaining' => $query
                ->orderByRaw('(COALESCE(slots_available, 0) - COALESCE(accepted_applications_count, 0)) DESC')
                ->orderBy('title'),
            'allowance' => $query
                ->orderByRaw("CAST(REPLACE(REPLACE(REPLACE(COALESCE(allowance, '0'), ',', ''), '₱', ''), ' ', '') AS REAL) DESC")
                ->orderBy('title'),
            'title' => $query->orderBy('title'),
            default => $query->orderByDesc('created_at')->orderBy('title'),
        };
    }

    /**
     * @return array{studentSchoolId:?int,partnerCompanyUserIds:Collection<int,int|string>,savedInternshipIds:array<int,true>}|null
     */
    private function viewerPartnershipContext(Request $request): ?array
    {
        $user = $request->user();
        if (! $user || $user->effectiveAppRole() !== 'student' || ! $user->student) {
            return null;
        }

        /** @var Student $student */
        $student = $user->student;
        $school = $this->internshipEligibility->resolveStudentSchool($student);
        $savedInternshipIds = SavedInternship::query()
            ->where('user_id', $user->id)
            ->pluck('internship_id')
            ->mapWithKeys(fn ($id) => [(int) $id => true])
            ->all();

        if (! $school || ! $school->user_id) {
            return [
                'studentSchoolId' => $school?->id,
                'partnerCompanyUserIds' => collect(),
                'savedInternshipIds' => $savedInternshipIds,
            ];
        }

        $partnerIds = Contract::query()
            ->where('school_user_id', $school->user_id)
            ->where('status', 'active')
            ->pluck('company_user_id')
            ->filter()
            ->values();

        return [
            'studentSchoolId' => (int) $school->id,
            'partnerCompanyUserIds' => $partnerIds,
            'savedInternshipIds' => $savedInternshipIds,
        ];
    }

    /**
     * @param  array{studentSchoolId:?int,partnerCompanyUserIds:Collection<int,int|string>,savedInternshipIds?:array<int,true>}|null  $viewerContext
     */
    private function resolveSavedFlag(Internship $internship, ?array $viewerContext): ?bool
    {
        if ($viewerContext === null || ! array_key_exists('savedInternshipIds', $viewerContext)) {
            return null;
        }

        return isset($viewerContext['savedInternshipIds'][(int) $internship->id]);
    }

    /**
     * @param  array{studentSchoolId:?int,partnerCompanyUserIds:Collection<int,int|string>}|null  $viewerContext
     */
    private function resolvePartneredWithMySchool(Internship $internship, string $hostType, ?array $viewerContext): ?bool
    {
        if ($viewerContext === null) {
            return null;
        }

        if ($hostType === 'school') {
            return $viewerContext['studentSchoolId'] !== null
                && (int) $internship->school_id === (int) $viewerContext['studentSchoolId'];
        }

        $companyUserId = $internship->company?->user_id;
        if (! $companyUserId) {
            return false;
        }

        return $viewerContext['partnerCompanyUserIds']
            ->map(fn ($id) => (int) $id)
            ->contains((int) $companyUserId);
    }

    private function resolveVerifiedFlag(Internship $internship, string $hostType): bool
    {
        if ($hostType === 'school') {
            return strtolower((string) ($internship->school?->verification_status ?? '')) === 'approved';
        }

        return strtolower((string) ($internship->company?->verification_status ?? '')) === 'approved';
    }

    private function resolveCoverImageUrl(Internship $internship, string $hostType): ?string
    {
        $cover = $this->resolveOrganizationMediaGallery($internship, $hostType)[0]['url'] ?? null;

        return is_string($cover) ? $cover : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function resolveOrganizationMediaGallery(Internship $internship, string $hostType): array
    {
        $organization = $hostType === 'school'
            ? $internship->school?->organization
            : $internship->company?->organization;

        if (! $organization) {
            return [];
        }

        if ($organization->relationLoaded('media')) {
            $items = $organization->media
                ->sortBy([
                    ['sort_order', 'asc'],
                    ['id', 'asc'],
                ])
                ->values();
        } else {
            $items = OrganizationMedia::query()
                ->where('organization_id', $organization->id)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();
        }

        // Prefer cover first for gallery UX, then remaining by sort_order.
        $sorted = $items->sortBy(function (OrganizationMedia $media) {
            return [(int) (! $media->is_cover), (int) $media->sort_order, (int) $media->id];
        })->values();

        return $sorted->map(fn (OrganizationMedia $media) => [
            'id' => (string) $media->id,
            'url' => $media->publicUrl(),
            'caption' => $media->caption,
            'sortOrder' => (int) $media->sort_order,
            'isCover' => (bool) $media->is_cover,
        ])->all();
    }

    private function normalizeWorkSetup(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $raw = strtolower(trim((string) $value));
        $raw = str_replace(['_', ' '], '-', $raw);

        return match ($raw) {
            'onsite', 'on-site', 'on site' => 'onsite',
            'hybrid' => 'hybrid',
            'remote', 'work-from-home', 'wfh' => 'remote',
            'field', 'field-work', 'fieldwork' => 'onsite',
            default => in_array($raw, ['onsite', 'hybrid', 'remote'], true) ? $raw : ($raw !== '' ? $raw : null),
        };
    }

    private function extractCityFromLocation(?string $location): ?string
    {
        if ($location === null) {
            return null;
        }

        $trimmed = trim($location);
        if ($trimmed === '') {
            return null;
        }

        return trim(explode(',', $trimmed)[0] ?? $trimmed) ?: null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{
     *   schedule: ?string,
     *   schedule_type: ?string,
     *   weekly_hours: mixed,
     *   schedule_days: ?array,
     *   time_in: ?string,
     *   time_out: ?string,
     *   timezone: string,
     *   is_flexible: bool
     * }
     */
    private function extractScheduleFields(array $data): array
    {
        $scheduleDays = isset($data['schedule_days']) && is_array($data['schedule_days'])
            ? array_values(array_filter(array_map(fn ($day) => is_string($day) ? trim($day) : '', $data['schedule_days'])))
            : null;
        $timeIn = isset($data['time_in']) ? (string) $data['time_in'] : null;
        $timeOut = isset($data['time_out']) ? (string) $data['time_out'] : null;
        $legacySchedule = isset($data['schedule']) ? (string) $data['schedule'] : null;

        $built = $this->buildScheduleString($scheduleDays, $timeIn, $timeOut);

        return [
            'schedule' => $built !== '' ? $built : ($legacySchedule ?: null),
            'schedule_type' => $data['schedule_type'] ?? null,
            'weekly_hours' => $data['weekly_hours'] ?? null,
            'schedule_days' => $scheduleDays,
            'time_in' => $timeIn,
            'time_out' => $timeOut,
            'timezone' => $data['timezone'] ?? 'Asia/Manila',
            'is_flexible' => (bool) ($data['is_flexible'] ?? false),
        ];
    }

    /**
     * @param  array<int, string>|null  $days
     */
    private function buildScheduleString(?array $days, ?string $timeIn, ?string $timeOut): string
    {
        $daysPart = $days ? implode(', ', $days) : '';
        $timePart = collect([$timeIn, $timeOut])->filter()->implode(' - ');

        return collect([$daysPart, $timePart])->filter()->implode(' | ');
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
