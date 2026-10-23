<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\PlacementSchedule;
use App\Services\OjtCalendarService;
use App\Services\PermissionGate;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class OjtCalendarController extends Controller
{
    public function __construct(
        private readonly OjtCalendarService $calendar,
        private readonly PermissionGate $permissions,
    ) {
    }

    public function show(Request $request, Application $application)
    {
        $user = $request->user();
        if (! $this->canView($user, $application)) {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $data = $request->validate([
            'month' => ['nullable', 'regex:/^\d{4}-\d{2}$/'],
        ]);

        return response()->json([
            'data' => $this->calendar->build($application, $data['month'] ?? null),
        ]);
    }

    public function updateStartDate(Request $request, Application $application)
    {
        $user = $request->user();
        $appRole = $user->effectiveAppRole();

        if ($appRole === 'student') {
            return response()->json(['message' => 'Students cannot set the OJT start date.'], Response::HTTP_FORBIDDEN);
        }
        if ($appRole === 'school' && ! $this->isSchoolHostOf($user, $application)) {
            return response()->json(['message' => 'Schools can view the calendar but cannot set the host start date.'], Response::HTTP_FORBIDDEN);
        }
        if (! $this->canHostManage($user, $application)) {
            return response()->json(['message' => 'Forbidden.'], Response::HTTP_FORBIDDEN);
        }
        if ($appRole !== 'admin' && ! $this->permissions->userCanAny($user, ['org.approve_applications', 'org.manage_applications'])) {
            return response()->json(['message' => 'Missing permission to set the OJT start date.'], Response::HTTP_FORBIDDEN);
        }

        $data = $request->validate([
            'ojt_start_date' => ['required', 'date'],
        ]);

        $schedule = PlacementSchedule::query()->where('application_id', $application->id)->first();
        if (! $schedule) {
            throw ValidationException::withMessages([
                'ojt_start_date' => ['No placement schedule exists yet. Accept the application first.'],
            ]);
        }

        if ($schedule->ojt_start_locked_at !== null || $this->calendar->hasApprovedDtr($application)) {
            throw ValidationException::withMessages([
                'ojt_start_date' => ['Start date is locked after the first approved DTR.'],
            ]);
        }

        $tz = OjtCalendarService::TIMEZONE;
        $start = Carbon::parse($data['ojt_start_date'], $tz)->startOfDay();
        $earliest = Carbon::now($tz)->startOfDay()->subDays(OjtCalendarService::START_DATE_MAX_PAST_DAYS);
        $latest = Carbon::now($tz)->startOfDay()->addYear();

        if ($start->lt($earliest)) {
            throw ValidationException::withMessages([
                'ojt_start_date' => ['Start date cannot be more than '.OjtCalendarService::START_DATE_MAX_PAST_DAYS.' days in the past.'],
            ]);
        }
        if ($start->gt($latest)) {
            throw ValidationException::withMessages([
                'ojt_start_date' => ['Start date cannot be more than one year in the future.'],
            ]);
        }

        $schedule->forceFill(['ojt_start_date' => $start->toDateString()])->save();

        // Keep the first schedule version aligned if it was the only version at acceptance.
        $versionQuery = \App\Models\PlacementScheduleVersion::query()->where('application_id', $application->id);
        if ($versionQuery->count() === 1) {
            $versionQuery->orderBy('id')->first()?->forceFill([
                'effective_from' => $start->toDateString(),
            ])->save();
        }

        return response()->json([
            'data' => $this->calendar->build($application->fresh()),
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
