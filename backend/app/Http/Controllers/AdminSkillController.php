<?php

namespace App\Http\Controllers;

use App\Models\Skill;
use App\Services\SkillCatalogService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AdminSkillController extends Controller
{
    public function __construct(private readonly SkillCatalogService $skills)
    {
    }

    private function assertAdmin(Request $request): ?\Illuminate\Http\JsonResponse
    {
        if ($request->user()?->effectiveAppRole() !== 'admin') {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        return null;
    }

    public function pending(Request $request)
    {
        if ($denied = $this->assertAdmin($request)) {
            return $denied;
        }

        $rows = Skill::query()
            ->pending()
            ->whereNull('merged_into_skill_id')
            ->with(['creator:id,name,email'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'data' => $rows->map(fn (Skill $skill) => [
                ...$skill->toApiArray(),
                'createdByName' => $skill->creator?->name,
                'createdByEmail' => $skill->creator?->email,
                'createdAt' => $skill->created_at?->toIso8601String(),
                'studentCount' => $skill->students()->count(),
                'jobCount' => $skill->internships()->count(),
            ])->values(),
        ]);
    }

    public function index(Request $request)
    {
        if ($denied = $this->assertAdmin($request)) {
            return $denied;
        }

        $query = Skill::query()->whereNull('merged_into_skill_id')->orderBy('name');
        if ($request->filled('status')) {
            $query->where('status', (string) $request->input('status'));
        }
        if ($request->filled('q')) {
            $q = trim((string) $request->input('q'));
            $query->where('name', 'like', '%'.$q.'%');
        }

        return response()->json([
            'data' => $query->limit(200)->get()->map(fn (Skill $skill) => $skill->toApiArray())->values(),
        ]);
    }

    public function approve(Request $request, Skill $skill)
    {
        if ($denied = $this->assertAdmin($request)) {
            return $denied;
        }

        $approved = $this->skills->approve($skill);

        return response()->json([
            'data' => $approved->toApiArray(),
            'message' => 'Skill approved and added to the shared list.',
        ]);
    }

    public function merge(Request $request, Skill $skill)
    {
        if ($denied = $this->assertAdmin($request)) {
            return $denied;
        }

        $data = $request->validate([
            'target_skill_id' => ['required', 'integer', 'exists:skills,id'],
        ]);

        $target = Skill::query()->findOrFail((int) $data['target_skill_id']);
        $merged = $this->skills->merge($skill, $target);

        return response()->json([
            'data' => $merged->toApiArray(),
            'message' => 'Custom skill merged. Linked profiles and jobs now use “'.$merged->name.'”.',
        ]);
    }

    public function reject(Request $request, Skill $skill)
    {
        if ($denied = $this->assertAdmin($request)) {
            return $denied;
        }

        $rejected = $this->skills->reject($skill);

        return response()->json([
            'data' => $rejected->toApiArray(),
            'message' => 'Skill rejected and removed from profiles and job postings.',
        ]);
    }
}
