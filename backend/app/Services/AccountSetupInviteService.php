<?php

namespace App\Services;

use App\Mail\StaffAccountSetupMail;
use App\Models\AccountSetupToken;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AccountSetupInviteService
{
    /**
     * Issue a hashed setup token and build the frontend setup URL.
     * Callers must never log the plain token or full setup URL.
     *
     * @return array{expiresAt: \Illuminate\Support\Carbon, setupUrl: string}
     */
    public function createToken(User $user, User $invitedBy, int $expiryHours): array
    {
        AccountSetupToken::query()
            ->where('user_id', $user->id)
            ->whereNull('used_at')
            ->delete();

        $plainToken = Str::random(64);
        $expiresAt = now()->addHours($expiryHours);

        AccountSetupToken::query()->create([
            'user_id' => $user->id,
            'invited_by_user_id' => $invitedBy->id,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => $expiresAt,
        ]);

        $frontendBase = rtrim((string) env('FRONTEND_URL', env('APP_URL', 'http://localhost:5173')), '/');
        $setupUrl = "{$frontendBase}/account-setup?token={$plainToken}";

        return [
            'expiresAt' => $expiresAt,
            'setupUrl' => $setupUrl,
        ];
    }

    /**
     * Create a staff setup token and send the invite email synchronously.
     *
     * @return array{sent: bool, expiresAt: string, errorMessage?: string}
     */
    public function sendStaffInvite(
        User $user,
        Organization $organization,
        User $invitedBy,
        int $expiryHours = 24,
        bool $forResend = false,
    ): array {
        $issued = $this->createToken($user, $invitedBy, $expiryHours);

        try {
            Mail::to($user->email)->send(new StaffAccountSetupMail(
                recipientName: $user->name,
                organizationName: $organization->name,
                setupUrl: $issued['setupUrl'],
                expiryHours: $expiryHours,
            ));

            return [
                'sent' => true,
                'expiresAt' => $issued['expiresAt']->toIso8601String(),
            ];
        } catch (\Throwable $exception) {
            report($exception);

            return [
                'sent' => false,
                'expiresAt' => $issued['expiresAt']->toIso8601String(),
                'errorMessage' => $forResend
                    ? 'The setup link was generated, but the email could not be delivered.'
                    : 'The account was created, but the setup email could not be sent.',
            ];
        }
    }
}
