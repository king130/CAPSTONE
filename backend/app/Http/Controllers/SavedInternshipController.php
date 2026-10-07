<?php

namespace App\Http\Controllers;

use App\Models\Internship;
use App\Models\SavedInternship;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SavedInternshipController extends Controller
{
    public function __construct(
        private readonly InternshipController $internships,
    ) {
    }

    public function index(Request $request)
    {
        $user = $this->requireStudent($request);
        if ($user instanceof \Illuminate\Http\JsonResponse) {
            return $user;
        }

        $viewerContext = $this->internships->catalogViewerContext($request);
        $perPage = max(1, min(100, (int) $request->input('per_page', 15)));

        $paginator = Internship::query()
            ->select('internships.*')
            ->join('saved_internships', 'saved_internships.internship_id', '=', 'internships.id')
            ->where('saved_internships.user_id', $user->id)
            ->where('internships.status', 'active')
            ->with($this->internships->catalogRelations())
            ->withCount([
                'applications as accepted_applications_count' => fn (Builder $builder) => $builder->where('status', 'accepted'),
            ])
            ->orderByDesc('saved_internships.created_at')
            ->orderByDesc('internships.id')
            ->paginate($perPage);

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

    public function store(Request $request)
    {
        $user = $this->requireStudent($request);
        if ($user instanceof \Illuminate\Http\JsonResponse) {
            return $user;
        }

        $data = $request->validate([
            'internship_id' => ['required', 'integer', 'exists:internships,id'],
        ]);

        $internship = Internship::query()->find((int) $data['internship_id']);
        if (! $internship || $internship->status !== 'active') {
            return response()->json([
                'message' => 'Only active internships can be saved.',
                'errors' => ['internship_id' => ['Only active internships can be saved.']],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        SavedInternship::query()->firstOrCreate([
            'user_id' => $user->id,
            'internship_id' => $internship->id,
        ]);

        $internship->load($this->internships->catalogRelations());
        $internship->loadCount([
            'applications as accepted_applications_count' => fn (Builder $builder) => $builder->where('status', 'accepted'),
        ]);

        return response()->json([
            'data' => $this->internships->catalogSerialize($internship, $this->internships->catalogViewerContext($request)),
        ], Response::HTTP_CREATED);
    }

    public function destroy(Request $request, int $internshipId)
    {
        $user = $this->requireStudent($request);
        if ($user instanceof \Illuminate\Http\JsonResponse) {
            return $user;
        }

        SavedInternship::query()
            ->where('user_id', $user->id)
            ->where('internship_id', $internshipId)
            ->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    private function requireStudent(Request $request): User|\Illuminate\Http\JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if (! $user || $user->effectiveAppRole() !== 'student') {
            return response()->json(['message' => 'Only students can manage saved internships.'], Response::HTTP_FORBIDDEN);
        }

        return $user;
    }
}
