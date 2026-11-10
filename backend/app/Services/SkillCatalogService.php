<?php

namespace App\Services;

use App\Models\Internship;
use App\Models\Skill;
use App\Models\Student;
use App\Models\User;
use App\Support\SkillNameNormalizer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SkillCatalogService
{
    /**
     * @return Collection<int, Skill>
     */
    public function findSuggestions(string $name, ?User $user = null, int $limit = 5): Collection
    {
        $normalized = SkillNameNormalizer::normalize($name);
        if ($normalized === '') {
            return collect();
        }

        $candidates = Skill::query()
            ->visibleTo($user)
            ->whereNull('merged_into_skill_id')
            ->orderBy('name')
            ->limit(400)
            ->get();

        return $candidates
            ->filter(fn (Skill $skill) => SkillNameNormalizer::isSimilar($normalized, $skill->normalized_name))
            ->take($limit)
            ->values();
    }

    public function createCustomSkill(User $user, string $name, string $category): Skill
    {
        $display = SkillNameNormalizer::displayName($name);
        $normalized = SkillNameNormalizer::normalize($display);
        if ($normalized === '') {
            throw ValidationException::withMessages([
                'name' => ['Enter a skill name.'],
            ]);
        }

        $exactApproved = Skill::query()
            ->approved()
            ->where('normalized_name', $normalized)
            ->whereNull('merged_into_skill_id')
            ->first();
        if ($exactApproved) {
            throw ValidationException::withMessages([
                'name' => ['This skill already exists in the shared list. Pick “'.$exactApproved->name.'” instead.'],
                'suggestion' => [$exactApproved->name],
                'suggestion_id' => [(string) $exactApproved->id],
            ]);
        }

        $existingPending = Skill::query()
            ->pending()
            ->where('normalized_name', $normalized)
            ->where('created_by_user_id', $user->id)
            ->whereNull('merged_into_skill_id')
            ->first();
        if ($existingPending) {
            return $existingPending;
        }

        return Skill::query()->create([
            'name' => $display,
            'normalized_name' => $normalized,
            'category' => SkillNameNormalizer::displayName($category) ?: 'Other',
            'status' => Skill::STATUS_PENDING,
            'created_by_user_id' => $user->id,
        ]);
    }

    /**
     * @param  array<int, array{id:int|string, level?:string}>  $items
     */
    public function syncStudentSkills(Student $student, array $items): void
    {
        if (count($items) > Skill::MAX_STUDENT) {
            throw ValidationException::withMessages([
                'skills' => ['You can add up to '.Skill::MAX_STUDENT.' skills on your profile.'],
            ]);
        }

        $sync = [];
        foreach ($items as $item) {
            $skill = $this->resolveUsableSkill((int) ($item['id'] ?? 0), $student->user);
            $level = strtolower(trim((string) ($item['level'] ?? 'beginner')));
            if (! in_array($level, ['beginner', 'intermediate', 'advanced'], true)) {
                $level = 'beginner';
            }
            $sync[$skill->id] = ['level' => $level];
        }

        $student->skills()->sync($sync);
    }

    /**
     * @param  array<int, array{id:int|string, importance?:string}>  $items
     */
    public function syncInternshipSkills(Internship $internship, array $items, ?User $actor = null): void
    {
        if (count($items) > Skill::MAX_JOB) {
            throw ValidationException::withMessages([
                'skills' => ['A job posting can list up to '.Skill::MAX_JOB.' skills.'],
            ]);
        }

        $sync = [];
        $requiredNames = [];
        foreach ($items as $item) {
            $skill = $this->resolveUsableSkill((int) ($item['id'] ?? 0), $actor);
            $importance = strtolower(trim((string) ($item['importance'] ?? 'required')));
            if (! in_array($importance, ['required', 'nice_to_have'], true)) {
                $importance = 'required';
            }
            $sync[$skill->id] = ['importance' => $importance];
            if ($importance === 'required') {
                $requiredNames[] = $skill->name;
            }
        }

        $internship->catalogSkills()->sync($sync);

        // Keep legacy JSON arrays in sync for older clients/displays.
        $internship->required_skills = array_values(array_unique(array_map(
            fn ($id) => Skill::query()->find($id)?->name,
            array_keys($sync)
        )));
        $internship->required_skills = array_values(array_filter($internship->required_skills ?? []));
        $internship->requirements = $requiredNames !== []
            ? $requiredNames
            : ($internship->required_skills ?? []);
        $internship->save();
    }

    public function resolveUsableSkill(int $skillId, ?User $user): Skill
    {
        $skill = Skill::query()->find($skillId);
        if (! $skill || $skill->status === Skill::STATUS_REJECTED) {
            throw ValidationException::withMessages([
                'skills' => ['One of the selected skills is no longer available.'],
            ]);
        }

        if ($skill->merged_into_skill_id) {
            $target = Skill::query()->find($skill->merged_into_skill_id);
            if ($target) {
                return $this->resolveUsableSkill((int) $target->id, $user);
            }
        }

        if ($skill->status === Skill::STATUS_PENDING) {
            if (! $user || (int) $skill->created_by_user_id !== (int) $user->id) {
                throw ValidationException::withMessages([
                    'skills' => ['Custom skills pending review are only available to the person who added them.'],
                ]);
            }
        }

        return $skill;
    }

    public function approve(Skill $skill): Skill
    {
        if ($skill->status === Skill::STATUS_APPROVED) {
            return $skill;
        }

        $duplicate = Skill::query()
            ->approved()
            ->where('normalized_name', $skill->normalized_name)
            ->where('id', '!=', $skill->id)
            ->whereNull('merged_into_skill_id')
            ->first();
        if ($duplicate) {
            return $this->merge($skill, $duplicate);
        }

        $skill->status = Skill::STATUS_APPROVED;
        $skill->merged_into_skill_id = null;
        $skill->save();

        return $skill->fresh();
    }

    public function merge(Skill $source, Skill $target): Skill
    {
        if ($source->id === $target->id) {
            throw ValidationException::withMessages([
                'target_skill_id' => ['Pick a different skill to merge into.'],
            ]);
        }

        DB::transaction(function () use ($source, $target) {
            // Remap student pivots.
            $studentRows = DB::table('student_skill')->where('skill_id', $source->id)->get();
            foreach ($studentRows as $row) {
                $exists = DB::table('student_skill')
                    ->where('student_id', $row->student_id)
                    ->where('skill_id', $target->id)
                    ->exists();
                if ($exists) {
                    DB::table('student_skill')->where('id', $row->id)->delete();
                } else {
                    DB::table('student_skill')->where('id', $row->id)->update(['skill_id' => $target->id]);
                }
            }

            $jobRows = DB::table('internship_skill')->where('skill_id', $source->id)->get();
            foreach ($jobRows as $row) {
                $exists = DB::table('internship_skill')
                    ->where('internship_id', $row->internship_id)
                    ->where('skill_id', $target->id)
                    ->exists();
                if ($exists) {
                    DB::table('internship_skill')->where('id', $row->id)->delete();
                } else {
                    DB::table('internship_skill')->where('id', $row->id)->update(['skill_id' => $target->id]);
                }
            }

            $source->status = Skill::STATUS_REJECTED;
            $source->merged_into_skill_id = $target->id;
            $source->save();

            if ($target->status !== Skill::STATUS_APPROVED) {
                $target->status = Skill::STATUS_APPROVED;
                $target->save();
            }
        });

        return $target->fresh();
    }

    public function reject(Skill $skill): Skill
    {
        DB::transaction(function () use ($skill) {
            DB::table('student_skill')->where('skill_id', $skill->id)->delete();
            DB::table('internship_skill')->where('skill_id', $skill->id)->delete();
            $skill->status = Skill::STATUS_REJECTED;
            $skill->save();
        });

        return $skill->fresh();
    }

    /**
     * Migrate free-text skills from internship JSON and keep leftovers as pending customs.
     */
    public function migrateLegacyFreeText(?callable $logger = null): array
    {
        $stats = ['matched' => 0, 'created_pending' => 0, 'internships' => 0];

        Internship::query()->orderBy('id')->chunkById(50, function ($internships) use (&$stats, $logger) {
            foreach ($internships as $internship) {
                /** @var Internship $internship */
                $names = collect()
                    ->merge(is_array($internship->required_skills) ? $internship->required_skills : [])
                    ->merge(is_array($internship->requirements) ? $internship->requirements : [])
                    ->map(fn ($n) => SkillNameNormalizer::displayName((string) $n))
                    ->filter()
                    ->unique(fn ($n) => SkillNameNormalizer::normalize($n))
                    ->take(Skill::MAX_JOB)
                    ->values();

                if ($names->isEmpty()) {
                    continue;
                }

                $items = [];
                foreach ($names as $name) {
                    $normalized = SkillNameNormalizer::normalize($name);
                    $approved = Skill::query()
                        ->approved()
                        ->where('normalized_name', $normalized)
                        ->whereNull('merged_into_skill_id')
                        ->first();

                    if (! $approved) {
                        $approved = Skill::query()
                            ->approved()
                            ->whereNull('merged_into_skill_id')
                            ->get()
                            ->first(fn (Skill $skill) => SkillNameNormalizer::isSimilar($normalized, $skill->normalized_name));
                    }

                    if ($approved) {
                        $items[] = ['id' => $approved->id, 'importance' => 'required'];
                        $stats['matched']++;
                    } else {
                        $pending = Skill::query()->create([
                            'name' => $name,
                            'normalized_name' => $normalized,
                            'category' => 'Other',
                            'status' => Skill::STATUS_PENDING,
                            'created_by_user_id' => $internship->company?->user_id,
                        ]);
                        $items[] = ['id' => $pending->id, 'importance' => 'required'];
                        $stats['created_pending']++;
                    }
                }

                $actor = $internship->company?->user;
                $this->syncInternshipSkills($internship, $items, $actor);
                $stats['internships']++;
                if ($logger) {
                    $logger("Migrated internship #{$internship->id}");
                }
            }
        });

        return $stats;
    }
}
