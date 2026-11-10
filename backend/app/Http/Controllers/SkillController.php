<?php

namespace App\Http\Controllers;

use App\Models\Skill;
use App\Services\SkillCatalogService;
use App\Support\CourseSkillCategoryDefaults;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class SkillController extends Controller
{
    public function __construct(private readonly SkillCatalogService $skills)
    {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $query = Skill::query()
            ->visibleTo($user)
            ->whereNull('merged_into_skill_id')
            ->orderBy('category')
            ->orderBy('name');

        if ($request->filled('category')) {
            $query->where('category', (string) $request->input('category'));
        }

        if ($request->filled('q')) {
            $q = trim((string) $request->input('q'));
            $query->where(function ($builder) use ($q) {
                $builder->where('name', 'like', '%'.$q.'%')
                    ->orWhere('normalized_name', 'like', '%'.mb_strtolower($q).'%');
            });
        }

        $skills = $query->get();
        $categories = Skill::query()
            ->visibleTo($user)
            ->whereNull('merged_into_skill_id')
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->values();

        $recommendedCategories = [];
        if ($user) {
            $student = $user->student;
            if ($student && ! $student->relationLoaded('school')) {
                $student->loadMissing('school');
            }
            $recommendedCategories = CourseSkillCategoryDefaults::forStudent($student);
        }

        return response()->json([
            'data' => $skills->map(fn (Skill $skill) => $skill->toApiArray())->values(),
            'categories' => $categories,
            'recommendedCategories' => $recommendedCategories,
            'limits' => [
                'student' => Skill::MAX_STUDENT,
                'job' => Skill::MAX_JOB,
            ],
        ]);
    }

    public function suggest(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $suggestions = $this->skills->findSuggestions($data['name'], $request->user());

        return response()->json([
            'data' => $suggestions->map(fn (Skill $skill) => $skill->toApiArray())->values(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:64'],
            'force' => ['sometimes', 'boolean'],
        ]);

        $suggestions = $this->skills->findSuggestions($data['name'], $request->user());
        if ($suggestions->isNotEmpty() && empty($data['force'])) {
            return response()->json([
                'message' => 'A similar skill already exists.',
                'suggestion' => $suggestions->first()->toApiArray(),
                'suggestions' => $suggestions->map(fn (Skill $skill) => $skill->toApiArray())->values(),
                'code' => 'skill_suggestion',
            ], Response::HTTP_CONFLICT);
        }

        try {
            $skill = $this->skills->createCustomSkill(
                $request->user(),
                $data['name'],
                (string) ($data['category'] ?? 'Other'),
            );
        } catch (ValidationException $e) {
            throw $e;
        }

        return response()->json([
            'data' => $skill->toApiArray(),
            'message' => 'Custom skill saved for review. You can use it meanwhile.',
        ], Response::HTTP_CREATED);
    }
}
