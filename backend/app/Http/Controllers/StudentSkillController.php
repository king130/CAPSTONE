<?php

namespace App\Http\Controllers;

use App\Models\Skill;
use App\Services\SkillCatalogService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class StudentSkillController extends Controller
{
    public function __construct(private readonly SkillCatalogService $skills)
    {
    }

    public function show(Request $request)
    {
        $user = $request->user();
        $student = $user?->student;
        if (! $student || $user->effectiveAppRole() !== 'student') {
            return response()->json(['message' => 'Only interns can manage profile skills.'], Response::HTTP_FORBIDDEN);
        }

        $student->load(['skills']);

        return response()->json([
            'data' => $student->skills->map(fn (Skill $skill) => [
                ...$skill->toApiArray(),
                'level' => $skill->pivot->level ?? 'beginner',
            ])->values(),
            'limit' => Skill::MAX_STUDENT,
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $student = $user?->student;
        if (! $student || $user->effectiveAppRole() !== 'student') {
            return response()->json(['message' => 'Only interns can manage profile skills.'], Response::HTTP_FORBIDDEN);
        }

        $data = $request->validate([
            'skills' => ['present', 'array', 'max:'.Skill::MAX_STUDENT],
            'skills.*.id' => ['required'],
            'skills.*.level' => ['nullable', 'in:beginner,intermediate,advanced'],
        ]);

        $this->skills->syncStudentSkills($student, $data['skills'] ?? []);
        $student->load(['skills']);

        return response()->json([
            'data' => $student->skills->map(fn (Skill $skill) => [
                ...$skill->toApiArray(),
                'level' => $skill->pivot->level ?? 'beginner',
            ])->values(),
            'message' => 'Skills saved.',
            'limit' => Skill::MAX_STUDENT,
        ]);
    }
}
