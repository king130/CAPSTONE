<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\PlacementScheduleRequest;
use App\Services\PermissionGate;
use App\Services\PlacementScheduleService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PlacementScheduleController extends Controller
{
    public function __construct(
        private readonly PlacementScheduleService $schedules,
        private readonly PermissionGate $permissions,
    ) {
    }

    public function show(Request $request, Application $application)
    {
        $user = $request->user();
        if (! $this->canView($user, $application)) {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        // Backfill schedule for apps accepted before placement-schedule support existed.
        $status = strtolower(trim((string) $application->status));
        $stage = strtolower(trim((string) ($application->tracker_stage ?? '')));
        if (in_array($status, ['accepted', 'approved', 'active'], true) || $stage === 'accepted') {
            $this->schedules->ensureOnAccepted($application);
            $application->refresh();
        }

        return response()->json([
            'data' => $this->schedules->serializeForApplication($application),
        ]);
    }

    public function requestChange(Request $request, Application $application)
    {
        $user = $request->user();
        if ($user->effectiveAppRole() !== 'student') {
            return response()->json(['message' => 'Only students can request schedule changes.'], Response::HTTP_FORBIDDEN);
        }
        if (! $user->student || (int) $application->student_id !== (int) $user->student->id) {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $data = $request->validate([
            'slots' => ['required', 'array', 'min:1'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $created = $this->schedules->requestChange(
            $application,
            $user,
            $data['slots'],
            $data['reason'],
        );

        return response()->json([
            'data' => [
                'request' => $this->schedules->serializeRequest($created),
                'schedule' => $this->schedules->serializeForApplication($application->fresh()),
            ],
        ], Response::HTTP_CREATED);
    }

    public function decide(Request $request, Application $application, PlacementScheduleRequest $scheduleRequest)
    {
        $user = $request->user();
        $appRole = $user->effectiveAppRole();

        if ($appRole === 'student') {
            return response()->json(['message' => 'Students cannot decide schedule requests.'], Response::HTTP_FORBIDDEN);
        }
        // Coordinating schools may view only. Hosting schools (school-hosted postings) may decide as host.
        if ($appRole === 'school' && ! $this->isSchoolHostOf($user, $application)) {
            return response()->json(['message' => 'Schools can view schedule requests but cannot approve or decline them.'], Response::HTTP_FORBIDDEN);
        }
        if ($appRole !== 'admin' && ! $this->permissions->userCanAny($user, ['org.approve_applications', 'org.manage_applications'])) {
            return response()->json(['message' => 'Missing permission to decide schedule requests.'], Response::HTTP_FORBIDDEN);
        }
        if (! $this->canHostManage($user, $application)) {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $data = $request->validate([
            'decision' => ['required', 'in:approved,declined'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $updated = $this->schedules->decide(
            $application,
            $scheduleRequest,
            $user,
            $data['decision'],
            $data['note'] ?? null,
        );

        return response()->json([
            'data' => [
                'request' => $this->schedules->serializeRequest($updated),
                'schedule' => $this->schedules->serializeForApplication($application->fresh()),
            ],
        ]);
    }

    private function canView($user, Application $application): bool
    {
        $appRole = $user->effectiveAppRole();
        if ($appRole === 'admin') {
            return true;
        }
        if ($appRole === 'student') {
            return $user->student && (int) $application->student_id === (int) $user->student->id;
        }
        if ($appRole === 'company') {
            return $this->canHostManage($user, $application);
        }
        if ($appRole === 'school') {
            $school = $user->organizationSchool() ?? $user->school;
            if (! $school || ! $application->student) {
                return false;
            }
            if ((int) $application->student->school_id === (int) $school->id) {
                return true;
            }
            if ($school->subscription_code && $application->student->school_subscription_code === $school->subscription_code) {
                return true;
            }
        }

        return false;
    }

    private function canHostManage($user, Application $application): bool
    {
        $appRole = $user->effectiveAppRole();
        if ($appRole === 'admin') {
            return true;
        }

        $company = $user->organizationCompany() ?? $user->company;
        if ($appRole === 'company' && $company) {
            return (int) $application->company_id === (int) $company->id;
        }

        return $this->isSchoolHostOf($user, $application);
    }

    private function isSchoolHostOf($user, Application $application): bool
    {
        $school = $user->organizationSchool() ?? $user->school;
        $internship = $application->internship ?? $application->internship()->first();
        if (! $school || ! $internship) {
            return false;
        }
        $isSchoolHosted = strtolower(trim((string) $internship->host_type)) === 'school'
            || ($internship->company_id === null && $internship->school_id !== null);

        return $isSchoolHosted && (int) $internship->school_id === (int) $school->id;
    }
}
