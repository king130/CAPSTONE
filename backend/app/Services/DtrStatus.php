<?php

namespace App\Services;

use App\Models\OjtDtrSubmission;

/**
 * Single status rule for every DTR write.
 *
 * Intern submits -> submitted, awaiting company review.
 * Company admin enters or approves -> approved.
 * School or system admin approves a row still in review -> approved.
 * Any rejection -> rejected, with a reason.
 *
 * School and system admin have no create path.
 */
class DtrStatus
{
    /**
     * @return array<string, mixed>
     */
    public static function forEntry(string $actorRole, int $actorUserId): array
    {
        if ($actorRole === 'student') {
            return [
                'status' => OjtDtrSubmission::STATUS_SUBMITTED,
                'review_stage' => OjtDtrSubmission::REVIEW_HOST,
                'company_reviewed_by_user_id' => null,
                'company_reviewed_at' => null,
                'reviewed_by_user_id' => null,
                'reviewed_at' => null,
                'rejection_reason' => null,
                'company_rejection_reason' => null,
            ];
        }

        if ($actorRole === 'company') {
            return self::approvedStamp('company', $actorUserId);
        }

        throw new \InvalidArgumentException('Only an intern or a company admin can enter a DTR.');
    }

    /**
     * @return array<string, mixed>
     */
    public static function forApproval(string $actorRole, int $actorUserId): array
    {
        return self::approvedStamp($actorRole, $actorUserId);
    }

    /**
     * @return array<string, mixed>
     */
    public static function forRejection(string $actorRole, int $actorUserId, string $reason): array
    {
        $reason = trim($reason);
        $stamp = [
            'status' => OjtDtrSubmission::STATUS_REJECTED,
            'review_stage' => null,
            'rejection_reason' => $reason,
        ];

        if ($actorRole === 'company') {
            $stamp['company_rejection_reason'] = $reason;
            $stamp['company_reviewed_by_user_id'] = $actorUserId;
            $stamp['company_reviewed_at'] = now();
        } else {
            $stamp['reviewed_by_user_id'] = $actorUserId;
            $stamp['reviewed_at'] = now();
        }

        return $stamp;
    }

    /**
     * @return array<string, mixed>
     */
    private static function approvedStamp(string $actorRole, int $actorUserId): array
    {
        $stamp = [
            'status' => OjtDtrSubmission::STATUS_APPROVED,
            'review_stage' => null,
            'rejection_reason' => null,
            'company_rejection_reason' => null,
        ];

        if ($actorRole === 'company') {
            $stamp['company_reviewed_by_user_id'] = $actorUserId;
            $stamp['company_reviewed_at'] = now();
        } else {
            $stamp['reviewed_by_user_id'] = $actorUserId;
            $stamp['reviewed_at'] = now();
        }

        return $stamp;
    }
}
