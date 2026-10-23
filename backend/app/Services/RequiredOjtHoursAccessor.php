<?php

namespace App\Services;

use App\Models\Application;
use App\Models\ApplicationHoursAgreement;

/**
 * Single read path for required OJT hours so a later phase can change the source.
 * Prefer an Agreed hours-agreement value; otherwise the application snapshot.
 */
class RequiredOjtHoursAccessor
{
    public function forApplication(Application $application): ?int
    {
        $application->loadMissing('hoursAgreement');
        $agreement = $application->hoursAgreement;
        if (
            $agreement
            && $agreement->status === ApplicationHoursAgreement::STATUS_AGREED
            && $agreement->agreed_hours !== null
            && (int) $agreement->agreed_hours >= 1
        ) {
            return (int) $agreement->agreed_hours;
        }

        if ($application->required_ojt_hours === null) {
            return null;
        }

        $hours = (int) $application->required_ojt_hours;

        return $hours > 0 ? $hours : null;
    }
}
