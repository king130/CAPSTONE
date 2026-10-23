<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Internship;
use App\Models\Organization;
use App\Models\School;
use App\Models\User;
use App\Support\CaviteAddress;
use App\Support\ProfileAvatar;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class OrganizationPublicController extends Controller
{
    public function __construct(
        private readonly InternshipController $internships,
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

        // `type=company` is the host browse entry: include municipal halls with companies.
        $typeFilter = $type === 'company'
            ? \App\Support\OrganizationTypes::hostTypes()
            : [$type];

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

        $facets = [
            'cities' => $this->discoverableCities($type),
            'industries' => \App\Support\OrganizationTypes::isHostType($type) ? $this->discoverableIndustries() : [],
        ];

        $perPage = max(1, min(24, (int) $request->input('per_page', 12)));
        $paginator = $query->orderBy('organizations.name')->paginate($perPage);

        return response()->json([
            'data' => collect($paginator->items())
                ->map(fn (Organization $organization) => $this->serializeDirectoryCard($organization, $type))
                ->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
            'facets' => $facets,
        ]);
    }

    public function show(Request $request, Organization $organization)
    {
        if (! $this->isPubliclyVisible($organization)) {
            return response()->json(['message' => 'Not found.'], Response::HTTP_NOT_FOUND);
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
     * @return array<int, string>
     */
    private function discoverableCities(string $type): array
    {
        $types = $type === 'company'
            ? \App\Support\OrganizationTypes::hostTypes()
            : [$type];

        return Organization::query()
            ->whereIn('type', $types)
            ->where('is_active', true)
            ->whereHas(
                \App\Support\OrganizationTypes::isHostType($type) ? 'companyProfile' : 'schoolProfile',
                function ($builder) {
                    $builder->whereRaw('LOWER(verification_status) = ?', ['approved']);
                }
            )
            ->whereNotNull('city')
            ->where('city', '!=', '')
            ->distinct()
            ->orderBy('city')
            ->pluck('city')
            ->map(fn ($city) => (string) $city)
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function discoverableIndustries(): array
    {
        $fromOrganizations = Organization::query()
            ->whereIn('type', \App\Support\OrganizationTypes::hostTypes())
            ->where('is_active', true)
            ->whereNotNull('industry')
            ->where('industry', '!=', '')
            ->distinct()
            ->pluck('industry');

        $fromCompanies = Company::query()
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
