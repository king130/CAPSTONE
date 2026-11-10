<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Internship;
use App\Models\Organization;
use App\Models\School;
use App\Models\User;
use App\Services\InternshipEligibilityService;
use App\Support\CaviteAddress;
use App\Support\CourseAlignment;
use App\Support\ProfileAvatar;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class OrganizationPublicController extends Controller
{
    public function __construct(
        private readonly InternshipController $internships,
        private readonly InternshipEligibilityService $internshipEligibility,
    ) {
    }

    public function index(Request $request)
    {
        $type = strtolower(trim((string) $request->input('type', '')));
        if (! in_array($type, ['company', 'school', 'municipal_hall'], true)) {
            return response()->json([
                'message' => 'Choose company, municipal_hall, or school.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        /** @var User|null $viewer */
        $viewer = $request->user();
        $role = $viewer?->effectiveAppRole();
        $isHostDirectory = \App\Support\OrganizationTypes::isHostType($type);
        $allowed = $isHostDirectory
            ? ['student', 'school', 'admin']
            : ['company', 'admin'];

        if (! in_array($role, $allowed, true)) {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        // Each browse page is isolated: company, municipal_hall, and school return only that type.
        $typeFilter = [$type];

        $query = Organization::query()
            ->whereIn('organizations.type', $typeFilter)
            ->where('organizations.is_active', true)
            ->whereHas($isHostDirectory ? 'companyProfile' : 'schoolProfile', function ($builder) {
                $builder->whereRaw('LOWER(verification_status) = ?', ['approved']);
            })
            ->with([
                'owner:id,profile,updated_at',
                'companyProfile:id,organization_id,company_address,industry_type,lgu_type,city_municipality,verification_status',
                'schoolProfile:id,organization_id,school_address,verification_status',
            ]);

        // Students may only browse host orgs their school has an active (student-visible) agreement with.
        $studentPartnerOrgIds = null;
        if ($role === 'student' && $isHostDirectory) {
            $studentPartnerOrgIds = $this->partnerHostOrganizationIdsForStudent($viewer);
            if ($studentPartnerOrgIds->isEmpty()) {
                return response()->json([
                    'data' => [],
                    'meta' => [
                        'current_page' => 1,
                        'per_page' => max(1, min(50, (int) $request->input('per_page', 10))),
                        'total' => 0,
                        'last_page' => 1,
                    ],
                    'facets' => [
                        'cities' => [],
                        'industries' => [],
                        'lgu_types' => [],
                    ],
                ]);
            }
            $query->whereIn('organizations.id', $studentPartnerOrgIds);
        }

        if ($isHostDirectory) {
            $query->withCount([
                'hostedInternships as open_opportunities_count' => fn ($builder) => $builder->where('internships.status', 'active'),
            ]);
        }

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(function ($builder) use ($like, $type) {
                $builder->where('organizations.name', 'like', $like)
                    ->orWhere('organizations.city', 'like', $like)
                    ->orWhere('organizations.industry', 'like', $like)
                    ->orWhere('organizations.address', 'like', $like)
                    ->orWhere('organizations.tagline', 'like', $like);
                if (\App\Support\OrganizationTypes::isHostType($type)) {
                    $builder->orWhereHas('companyProfile', fn ($company) => $company
                        ->where('company_address', 'like', $like)
                        ->orWhere('industry_type', 'like', $like));
                } else {
                    $builder->orWhereHas('schoolProfile', fn ($school) => $school->where('school_address', 'like', $like));
                }
            });
        }

        $city = trim((string) $request->input('city', ''));
        if ($city !== '') {
            $likeCity = '%'.addcslashes($city, '%_\\').'%';
            $query->where(function ($builder) use ($city, $likeCity, $type) {
                $builder->where('organizations.city', $city);
                if (\App\Support\OrganizationTypes::isHostType($type)) {
                    $builder->orWhereHas('companyProfile', fn ($company) => $company->where('company_address', 'like', $likeCity));
                } else {
                    $builder->orWhereHas('schoolProfile', fn ($school) => $school->where('school_address', 'like', $likeCity));
                }
            });
        }

        $industry = trim((string) $request->input('industry', ''));
        if ($industry !== '' && \App\Support\OrganizationTypes::isHostType($type)) {
            $query->where(function ($builder) use ($industry) {
                $builder->where('organizations.industry', $industry)
                    ->orWhereHas('companyProfile', fn ($company) => $company->where('industry_type', $industry));
            });
        }

        if ($request->boolean('has_openings') && \App\Support\OrganizationTypes::isHostType($type)) {
            $query->whereHas('hostedInternships', fn ($builder) => $builder->where('internships.status', 'active'));
        }

        $lguType = trim((string) $request->input('lgu_type', ''));
        if ($lguType !== '' && $type === 'municipal_hall') {
            $query->whereHas('companyProfile', fn ($company) => $company->where('lgu_type', $lguType));
        }

        $facets = [
            'cities' => $this->discoverableCities($type, $studentPartnerOrgIds),
            'industries' => \App\Support\OrganizationTypes::isHostType($type)
                ? $this->discoverableIndustries($type, $studentPartnerOrgIds)
                : [],
            'lgu_types' => $type === 'municipal_hall'
                ? $this->discoverableLguTypes($studentPartnerOrgIds)
                : [],
        ];

        $sort = strtolower(trim((string) $request->input('sort', 'name')));
        if ($sort === 'city') {
            $query->orderBy('organizations.city')->orderBy('organizations.name');
        } elseif ($sort === 'industry' && $isHostDirectory) {
            $query->orderBy('organizations.industry')->orderBy('organizations.name');
        } elseif (($sort === 'lgu_type' || $sort === 'lgutype') && $type === 'municipal_hall') {
            $query->leftJoin('companies as browse_companies', 'browse_companies.organization_id', '=', 'organizations.id')
                ->orderBy('browse_companies.lgu_type')
                ->orderBy('organizations.name')
                ->select('organizations.*');
        } elseif ($sort === 'openings' && $isHostDirectory) {
            $query->orderByDesc('open_opportunities_count')->orderBy('organizations.name');
        } else {
            $query->orderBy('organizations.name');
        }

        $perPage = max(1, min(50, (int) $request->input('per_page', 10)));
        $page = max(1, (int) $request->input('page', 1));

        $applyCourseAlignment = in_array($role, ['school', 'company'], true);
        $viewerCourses = $applyCourseAlignment ? $this->viewerOrganizationCourses($viewer) : [];
        $partneredOrgIds = $applyCourseAlignment
            ? $this->partneredOrganizationIdsForDirectoryViewer($viewer, $type)
            : collect();

        $requestedMode = strtolower(trim((string) $request->input('course_alignment', '')));
        if (! in_array($requestedMode, ['aligned', 'all'], true)) {
            $requestedMode = $viewerCourses !== [] ? 'aligned' : 'all';
        }
        // Without viewer courses there is nothing to align against — always show all.
        if ($viewerCourses === []) {
            $requestedMode = 'all';
        }

        if (! $applyCourseAlignment) {
            $paginator = $query->paginate($perPage);

            return response()->json([
                'data' => collect($paginator->items())
                    ->map(fn (Organization $organization) => $this->serializeDirectoryCard($organization, $type))
                    ->values(),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                    'course_alignment' => 'all',
                    'viewer_has_courses' => false,
                    'aligned' => 0,
                    'hidden_count' => 0,
                    'catalog_total' => $paginator->total(),
                ],
                'facets' => $facets,
            ]);
        }

        $organizations = $query->get();
        $cards = $organizations
            ->map(function (Organization $organization) use ($type, $viewerCourses, $partneredOrgIds) {
                $otherCourses = CourseAlignment::normalizeList($organization->owner?->profile['courses'] ?? []);
                $shared = CourseAlignment::sharedCourses($viewerCourses, $otherCourses);
                $alignment = CourseAlignment::alignment($viewerCourses, $otherCourses);
                $isPartnered = $partneredOrgIds->contains((int) $organization->id);

                return $this->serializeDirectoryCard($organization, $type) + [
                    'sharedCourses' => $shared,
                    'shared_courses' => $shared,
                    'alignment' => $alignment,
                    'isPartnered' => $isPartnered,
                    'is_partnered' => $isPartnered,
                ];
            })
            ->values();

        $catalogTotal = $cards->count();
        $alignedCount = $cards->where('alignment', CourseAlignment::ALIGNED)->count();
        $visible = $requestedMode === 'all'
            ? $cards
            : $cards->filter(
                fn (array $card) => CourseAlignment::includeWhenAlignedFilter(
                    (string) $card['alignment'],
                    (bool) $card['is_partnered'],
                )
            )->values();
        $hiddenCount = max(0, $catalogTotal - $visible->count());

        $paginator = new LengthAwarePaginator(
            $visible->forPage($page, $perPage)->values(),
            $visible->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        return response()->json([
            'data' => collect($paginator->items())->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'course_alignment' => $requestedMode,
                'viewer_has_courses' => $viewerCourses !== [],
                'aligned' => $alignedCount,
                'hidden_count' => $hiddenCount,
                'catalog_total' => $catalogTotal,
            ],
            'facets' => $facets,
        ]);
    }

    public function show(Request $request, Organization $organization)
    {
        if (! $this->isPubliclyVisible($organization)) {
            return response()->json(['message' => 'Not found.'], Response::HTTP_NOT_FOUND);
        }

        if ($blocked = $this->forbidStudentHostWithoutAgreement($request, $organization)) {
            return $blocked;
        }

        $payload = $this->serializePublic($organization);

        if (\App\Support\OrganizationTypes::isHostType($organization->type)) {
            $viewerContext = $this->internships->catalogViewerContext($request);
            $paginator = $this->companyInternshipsQuery($organization)
                ->paginate(max(1, min(100, (int) $request->input('per_page', 12))));

            $payload['internships'] = collect($paginator->items())
                ->map(fn (Internship $internship) => $this->internships->catalogSerialize($internship, $viewerContext))
                ->values();
            $payload['internships_meta'] = [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ];
        }

        return response()->json(['data' => $payload]);
    }

    public function internships(Request $request, Organization $organization)
    {
        if (! $this->isPubliclyVisible($organization)) {
            return response()->json(['message' => 'Not found.'], Response::HTTP_NOT_FOUND);
        }

        if ($blocked = $this->forbidStudentHostWithoutAgreement($request, $organization)) {
            return $blocked;
        }

        if (! \App\Support\OrganizationTypes::isHostType($organization->type)) {
            return response()->json([
                'data' => [],
                'meta' => [
                    'current_page' => 1,
                    'per_page' => 0,
                    'total' => 0,
                    'last_page' => 1,
                ],
            ]);
        }

        $viewerContext = $this->internships->catalogViewerContext($request);
        $perPage = max(1, min(100, (int) $request->input('per_page', 15)));
        $paginator = $this->companyInternshipsQuery($organization)->paginate($perPage);

        return response()->json([
            'data' => collect($paginator->items())
                ->map(fn (Internship $internship) => $this->internships->catalogSerialize($internship, $viewerContext))
                ->values(),
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

    private function isPubliclyVisible(Organization $organization): bool
    {
        return (bool) $organization->is_active
            && in_array($organization->type, ['school', 'company', 'municipal_hall'], true);
    }

    /**
     * @return \Illuminate\Support\Collection<int, int>
     */
    private function partnerHostOrganizationIdsForStudent(?User $viewer): \Illuminate\Support\Collection
    {
        if (! $viewer?->student) {
            return collect();
        }

        $school = $this->internshipEligibility->resolveStudentSchool($viewer->student);
        if (! $school) {
            return collect();
        }

        $companyIds = $this->internshipEligibility->activePartnerCompanyIds($school);
        if ($companyIds->isEmpty()) {
            return collect();
        }

        return Company::query()
            ->whereIn('id', $companyIds)
            ->whereNotNull('organization_id')
            ->pluck('organization_id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();
    }

    private function forbidStudentHostWithoutAgreement(Request $request, Organization $organization): ?\Illuminate\Http\JsonResponse
    {
        if (! \App\Support\OrganizationTypes::isHostType($organization->type)) {
            return null;
        }

        $viewer = $request->user();
        if ($viewer?->effectiveAppRole() !== 'student') {
            return null;
        }

        if (! $viewer->student) {
            return response()->json([
                'message' => 'Your student account is not linked to a school, so this organization is unavailable.',
                'code' => 'student_school_required',
            ], Response::HTTP_FORBIDDEN);
        }

        if (! $this->internshipEligibility->studentCanDiscoverHostOrganization($viewer->student, (int) $organization->id)) {
            return response()->json([
                'message' => 'This organization is not available because your school does not have an active agreement with them.',
                'code' => 'agreement_required',
            ], Response::HTTP_FORBIDDEN);
        }

        return null;
    }

    private function companyInternshipsQuery(Organization $organization): Builder
    {
        $companyId = Company::query()->where('organization_id', $organization->id)->value('id');

        return Internship::query()
            ->with($this->internships->catalogRelations())
            ->withCount([
                'applications as accepted_applications_count' => fn (Builder $builder) => $builder->where('status', 'accepted'),
            ])
            ->where('status', 'active')
            ->where('company_id', $companyId ?: 0)
            ->orderByDesc('created_at');
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePublic(Organization $organization): array
    {
        $organization->loadMissing('owner');

        $location = CaviteAddress::displayFor($organization);

        return [
            'id' => (string) $organization->id,
            'name' => $organization->name,
            'type' => $organization->type,
            'verified' => $this->resolveVerified($organization),
            'tagline' => $organization->tagline,
            'description' => $organization->description,
            'address' => $location['address'],
            'city' => $location['city'],
            'website' => $organization->website,
            'industry' => $organization->industry,
            'perks' => array_values($organization->perks ?? []),
            'cover_image' => ProfileAvatar::publicUrl($organization->owner),
            'coverImage' => ProfileAvatar::publicUrl($organization->owner),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeDirectoryCard(Organization $organization, string $type): array
    {
        if (trim((string) $organization->address) === '') {
            $fallback = \App\Support\OrganizationTypes::isHostType($type)
                ? $organization->companyProfile?->company_address
                : $organization->schoolProfile?->school_address;
            if (is_string($fallback) && trim($fallback) !== '') {
                $organization->address = trim($fallback);
            }
        }

        $location = CaviteAddress::displayFor($organization);
        $industry = trim((string) ($organization->industry ?: $organization->companyProfile?->industry_type ?: ''));

        return [
            'id' => (string) $organization->id,
            'name' => $organization->name,
            'type' => $organization->type ?: $type,
            'verified' => true,
            'tagline' => $organization->tagline,
            'industry' => $industry,
            'lguType' => $organization->companyProfile?->lgu_type,
            'address' => $location['address'],
            'city' => $location['city'],
            'logoUrl' => ProfileAvatar::publicUrl($organization->owner),
            'openOpportunities' => \App\Support\OrganizationTypes::isHostType($type)
                ? (int) ($organization->open_opportunities_count ?? 0)
                : 0,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, int>|null  $organizationIds
     * @return array<int, string>
     */
    private function discoverableCities(string $type, $organizationIds = null): array
    {
        $query = Organization::query()
            ->where('type', $type)
            ->where('is_active', true)
            ->whereHas(
                \App\Support\OrganizationTypes::isHostType($type) ? 'companyProfile' : 'schoolProfile',
                function ($builder) {
                    $builder->whereRaw('LOWER(verification_status) = ?', ['approved']);
                }
            )
            ->whereNotNull('city')
            ->where('city', '!=', '');

        if ($organizationIds !== null) {
            $query->whereIn('organizations.id', $organizationIds);
        }

        return $query
            ->distinct()
            ->orderBy('city')
            ->pluck('city')
            ->map(fn ($city) => (string) $city)
            ->values()
            ->all();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, int>|null  $organizationIds
     * @return array<int, string>
     */
    private function discoverableIndustries(string $type, $organizationIds = null): array
    {
        $fromOrganizations = Organization::query()
            ->where('type', $type)
            ->where('is_active', true)
            ->whereNotNull('industry')
            ->where('industry', '!=', '')
            ->when($organizationIds !== null, fn ($query) => $query->whereIn('organizations.id', $organizationIds))
            ->distinct()
            ->pluck('industry');

        $fromCompanies = Company::query()
            ->whereHas('organization', function ($query) use ($type, $organizationIds) {
                $query->where('type', $type)->where('is_active', true);
                if ($organizationIds !== null) {
                    $query->whereIn('organizations.id', $organizationIds);
                }
            })
            ->whereNotNull('industry_type')
            ->where('industry_type', '!=', '')
            ->whereRaw('LOWER(verification_status) = ?', ['approved'])
            ->distinct()
            ->pluck('industry_type');

        return $fromOrganizations
            ->merge($fromCompanies)
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, int>|null  $organizationIds
     * @return array<int, string>
     */
    private function discoverableLguTypes($organizationIds = null): array
    {
        return Company::query()
            ->whereHas(
                'organization',
                function ($query) use ($organizationIds) {
                    $query->where('type', 'municipal_hall')->where('is_active', true);
                    if ($organizationIds !== null) {
                        $query->whereIn('organizations.id', $organizationIds);
                    }
                },
            )
            ->whereRaw('LOWER(verification_status) = ?', ['approved'])
            ->whereNotNull('lgu_type')
            ->where('lgu_type', '!=', '')
            ->distinct()
            ->orderBy('lgu_type')
            ->pluck('lgu_type')
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Courses used for partner browse alignment — always the organization owner's profile,
     * never a non-owner member's personal profile.
     *
     * @return list<string>
     */
    private function viewerOrganizationCourses(?User $viewer): array
    {
        if (! $viewer) {
            return [];
        }

        $organization = $this->resolveViewerOrganization($viewer);
        if (! $organization) {
            return [];
        }

        $owner = $organization->relationLoaded('owner')
            ? $organization->owner
            : User::query()->find($organization->owner_user_id);

        if (! $owner) {
            return [];
        }

        $profile = is_array($owner->profile) ? $owner->profile : [];

        return CourseAlignment::normalizeList($profile['courses'] ?? []);
    }

    private function resolveViewerOrganization(?User $viewer): ?Organization
    {
        if (! $viewer) {
            return null;
        }

        $organization = $viewer->activeOrganization();
        if ($organization) {
            return $organization;
        }

        // Owners may browse before/without an active membership row — resolve via school/company profile.
        $school = $viewer->school;
        if ($school?->organization_id) {
            return Organization::query()->find($school->organization_id);
        }

        $company = $viewer->company;
        if ($company?->organization_id) {
            return Organization::query()->find($company->organization_id);
        }

        return Organization::query()->where('owner_user_id', $viewer->id)->first();
    }

    /**
     * Organization ids with an active (student-visible) agreement relative to the viewer.
     *
     * @return Collection<int, int>
     */
    private function partneredOrganizationIdsForDirectoryViewer(?User $viewer, string $listedType): Collection
    {
        if (! $viewer) {
            return collect();
        }

        $role = $viewer->effectiveAppRole();
        if ($role === 'school' && \App\Support\OrganizationTypes::isHostType($listedType)) {
            $school = $viewer->organizationSchool() ?? $viewer->school;
            if (! $school) {
                return collect();
            }

            return $this->internshipEligibility->activePartnerHostOrganizationIds($school)
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->unique()
                ->values();
        }

        if ($role === 'company' && $listedType === 'school') {
            $company = $viewer->organizationCompany() ?? $viewer->company;
            $viewerOrg = $viewer->activeOrganization();
            if (! $company && ! $viewerOrg) {
                return collect();
            }

            $agreements = $this->internshipEligibility->studentVisibleAgreementsQuery()
                ->where(function (Builder $query) use ($company, $viewerOrg) {
                    if ($company?->user_id) {
                        $query->where('company_user_id', $company->user_id);
                    }
                    if ($viewerOrg?->id) {
                        $method = $company?->user_id ? 'orWhere' : 'where';
                        $query->{$method}(function (Builder $orgs) use ($viewerOrg) {
                            $orgs->where('requester_organization_id', $viewerOrg->id)
                                ->orWhere('partner_organization_id', $viewerOrg->id);
                        });
                    }
                })
                ->get([
                    'school_user_id',
                    'requester_organization_id',
                    'partner_organization_id',
                ]);

            $organizationIds = collect();
            foreach ($agreements as $agreement) {
                foreach ([(int) $agreement->requester_organization_id, (int) $agreement->partner_organization_id] as $orgId) {
                    if ($orgId > 0 && $orgId !== (int) ($viewerOrg?->id ?? 0)) {
                        $organizationIds->push($orgId);
                    }
                }
                $schoolUserId = (int) ($agreement->school_user_id ?? 0);
                if ($schoolUserId > 0) {
                    $schoolOrgId = School::query()->where('user_id', $schoolUserId)->value('organization_id');
                    if ($schoolOrgId) {
                        $organizationIds->push((int) $schoolOrgId);
                    }
                }
            }

            return $organizationIds
                ->filter()
                ->unique()
                ->values();
        }

        return collect();
    }

    private function resolveVerified(Organization $organization): bool
    {
        if ($organization->type === 'school') {
            return School::query()
                ->where('organization_id', $organization->id)
                ->whereRaw('LOWER(verification_status) = ?', ['approved'])
                ->exists();
        }

        return Company::query()
            ->where('organization_id', $organization->id)
            ->whereRaw('LOWER(verification_status) = ?', ['approved'])
            ->exists();
    }
}
