<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Internship;
use App\Models\Organization;
use App\Models\OrganizationMedia;
use App\Models\School;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class OrganizationPublicController extends Controller
{
    public function __construct(
        private readonly InternshipController $internships,
    ) {
    }

    public function show(Request $request, Organization $organization)
    {
        if (! $this->isPubliclyVisible($organization)) {
            return response()->json(['message' => 'Not found.'], Response::HTTP_NOT_FOUND);
        }

        $payload = $this->serializePublic($organization);

        if ($organization->type === 'company') {
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

        if ($organization->type !== 'company') {
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
            && in_array($organization->type, ['school', 'company'], true);
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
        $organization->loadMissing('media');

        $cover = $organization->media->first(fn (OrganizationMedia $media) => $media->is_cover)
            ?? $organization->media->first();

        return [
            'id' => (string) $organization->id,
            'name' => $organization->name,
            'type' => $organization->type,
            'verified' => $this->resolveVerified($organization),
            'tagline' => $organization->tagline,
            'description' => $organization->description,
            'address' => $organization->address,
            'city' => $organization->city,
            'website' => $organization->website,
            'industry' => $organization->industry,
            'perks' => array_values($organization->perks ?? []),
            'cover_image' => $cover?->publicUrl(),
            'coverImage' => $cover?->publicUrl(),
        ];
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
