<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Services\HoursAgreementService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class HoursAgreementController extends Controller
{
    public function __construct(private readonly HoursAgreementService $hoursAgreements)
    {
    }

    public function show(Request $request, Application $application)
    {
        $user = $request->user();
        $this->hoursAgreements->assertCanAct($user, $application, false);
        $application->load(['hoursAgreement.events.changedBy', 'internship']);

        return response()->json([
            'data' => $this->hoursAgreements->serialize($application->hoursAgreement, $application, $application->internship),
        ]);
    }

    public function propose(Request $request, Application $application)
    {
        $data = $request->validate([
            'hours' => ['required', 'integer', 'min:1', 'max:'.HoursAgreementService::MAX_HOURS],
            'source' => ['nullable', 'string', 'in:school,host,custom'],
            'note' => ['nullable', 'string', 'max:2000'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $hours = (int) $data['hours'];
        if (($data['source'] ?? null) === 'school' && $application->hoursAgreement) {
            $hours = (int) $application->hoursAgreement->school_required_hours;
        } elseif (($data['source'] ?? null) === 'host' && $application->hoursAgreement) {
            $hours = (int) $application->hoursAgreement->host_offered_hours;
        }

        $agreement = $this->hoursAgreements->propose(
            $application,
            $request->user(),
            $hours,
            $data['note'] ?? null,
            $data['reason'] ?? null,
        );

        return response()->json([
            'data' => $this->hoursAgreements->serialize($agreement, $application->fresh(['internship']), $application->internship),
        ]);
    }

    public function confirm(Request $request, Application $application)
    {
        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $agreement = $this->hoursAgreements->confirm($application, $request->user(), $data['note'] ?? null);

        return response()->json([
            'data' => $this->hoursAgreements->serialize($agreement, $application->fresh(['internship']), $application->internship),
        ]);
    }

    public function decline(Request $request, Application $application)
    {
        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $agreement = $this->hoursAgreements->decline($application, $request->user(), $data['note'] ?? null);

        return response()->json([
            'data' => $this->hoursAgreements->serialize($agreement, $application->fresh(['internship']), $application->internship),
        ], Response::HTTP_OK);
    }
}
