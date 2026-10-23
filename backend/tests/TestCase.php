<?php

namespace Tests;

use App\Models\Document;
use App\Models\Organization;
use App\Models\SchoolCourseRequirement;
use App\Models\Student;
use App\Models\Subscription;
use App\Models\User;
use App\Services\SubscriptionAccessService;
use App\Support\InternshipDocumentRequirements;
use App\Support\StudentYearLevel;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;

abstract class TestCase extends BaseTestCase
{
    protected function seedEndorsementDocument(User $user): Document
    {
        $existing = Document::query()
            ->where('owner_id', $user->id)
            ->where('owner_type', 'student')
            ->whereIn('category', InternshipDocumentRequirements::endorsementDocumentCategories())
            ->first();

        if ($existing) {
            return $existing;
        }

        return Document::query()->create([
            'owner_id' => $user->id,
            'owner_type' => 'student',
            'category' => InternshipDocumentRequirements::ENDORSEMENT_CATEGORY,
            'file_name' => 'test-endorsement.pdf',
            'file_url' => 'http://127.0.0.1/testing/endorsement-'.$user->id.'.pdf',
            'file_type' => 'application/pdf',
            'file_size' => 100,
            'storage_provider' => 'local_private',
            'verified_at' => now(),
        ]);
    }

    /**
     * Ensure the student's school course has hours + eligible years so apply is not blocked (Phase B).
     * Does not overwrite an existing course's eligible year list.
     */
    protected function ensureStudentCourseReady(User $user): void
    {
        $student = Student::query()->where('user_id', $user->id)->first();
        if (! $student || ! $student->school_id) {
            return;
        }

        $course = trim((string) ($student->course ?? ''));
        if ($course === '') {
            $course = 'BS Computer Science';
            $student->course = $course;
        }

        $requirement = SchoolCourseRequirement::query()
            ->where('school_id', $student->school_id)
            ->where('course_name_key', SchoolCourseRequirement::courseNameKey($course))
            ->first();

        if (! $requirement) {
            $requirement = SchoolCourseRequirement::query()->create([
                'school_id' => $student->school_id,
                'course_name' => SchoolCourseRequirement::displayCourseName($course),
                'course_name_key' => SchoolCourseRequirement::courseNameKey($course),
                'required_hours' => 486,
                'eligible_year_levels' => StudentYearLevel::DEFAULT_ELIGIBLE,
            ]);
        } else {
            $dirty = false;
            if ((int) $requirement->required_hours < 1) {
                $requirement->required_hours = 486;
                $dirty = true;
            }
            if (StudentYearLevel::normalizeList($requirement->eligible_year_levels) === []) {
                $requirement->eligible_year_levels = StudentYearLevel::DEFAULT_ELIGIBLE;
                $dirty = true;
            }
            if ($dirty) {
                $requirement->save();
            }
        }

        $eligible = StudentYearLevel::normalizeList($requirement->fresh()->eligible_year_levels);
        if ($eligible === []) {
            $eligible = StudentYearLevel::DEFAULT_ELIGIBLE;
        }

        $year = StudentYearLevel::normalize($student->year_level);
        if ($year === null || ! StudentYearLevel::isEligible($year, $eligible)) {
            $student->year_level = in_array('4th Year', $eligible, true) ? '4th Year' : $eligible[0];
        }
        $student->save();
    }

    /**
     * Ensure an organization has writable subscription access for tests that create orgs
     * or rely on write routes (Phase H). Prefer active paid unless $asTrial is true.
     */
    protected function ensureOrganizationSubscriptionWritable(Organization $organization, bool $asTrial = false): Subscription
    {
        $access = app(SubscriptionAccessService::class);
        $subscription = $organization->subscription;
        if (! $subscription) {
            $subscription = Subscription::query()->create([
                'plan' => $access->trialPlanSlug(),
                'status' => 'trialing',
                'billing_cycle' => 'monthly',
            ]);
            $organization->subscription_id = $subscription->id;
            $organization->save();
        }

        if ($asTrial) {
            $access->startTrial($subscription, $organization);

            return $subscription->fresh();
        }

        $access->activatePaid(
            $subscription,
            'standard',
            $organization,
            null,
            'monthly',
            $access->now()->copy()->addYear(),
            'Test helper: active paid for suite compatibility.'
        );

        return $subscription->fresh();
    }

    /**
     * Apply to an internship with a seeded endorsement (Phase E) and course setup (Phase B).
     *
     * @param  array<string, mixed>  $payload
     */
    protected function postApplication(int|string $internshipId, array $payload = [], ?User $student = null): TestResponse
    {
        $user = $student ?? auth()->user();
        if (! $user instanceof User) {
            // Sanctum::actingAs may only set the request user; fall back to resolving later.
            $user = request()->user();
        }
        if ($user instanceof User) {
            $this->seedEndorsementDocument($user);
            $this->ensureStudentCourseReady($user);
        }

        return $this->postJson('/api/applications', array_merge([
            'internship_id' => (int) $internshipId,
        ], $payload));
    }
}
