<?php

namespace App\Services;

use App\Mail\OtpMail;
use App\Models\EmailOtp;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

class EmailOtpService
{
    public const CODE_LENGTH = 6;

    public const EXPIRES_MINUTES = 10;

    public const MAX_ATTEMPTS = 5;

    public const RESEND_COOLDOWN_SECONDS = 60;

    public function needsEmailVerification(User $user): bool
    {
        if ($user->email_verified_at !== null) {
            return false;
        }

        return in_array((string) $user->role, ['school', 'company', 'municipal_hall'], true);
    }

    /**
     * Issue a new OTP and send it synchronously. Never returns the plaintext code.
     *
     * @throws ValidationException
     * @throws Throwable mail failures bubble after the OTP row is stored so resend can retry
     */
    public function issueAndSend(User $user): void
    {
        if (! $this->needsEmailVerification($user)) {
            throw ValidationException::withMessages([
                'email' => ['This account does not require email verification.'],
            ]);
        }

        $latest = EmailOtp::query()
            ->where('user_id', $user->id)
            ->whereNull('consumed_at')
            ->orderByDesc('id')
            ->first();

        if ($latest && $latest->created_at && $latest->created_at->gt(now()->subSeconds(self::RESEND_COOLDOWN_SECONDS))) {
            $wait = self::RESEND_COOLDOWN_SECONDS - $latest->created_at->diffInSeconds(now());
            throw ValidationException::withMessages([
                'code' => ['Please wait '.$wait.' seconds before requesting a new code.'],
            ]);
        }

        EmailOtp::query()
            ->where('user_id', $user->id)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        $code = $this->generateCode();
        $otp = EmailOtp::query()->create([
            'user_id' => $user->id,
            'code_hash' => Hash::make($code),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(self::EXPIRES_MINUTES),
            'consumed_at' => null,
        ]);

        try {
            Mail::to($user->email)->send(new OtpMail(
                recipientName: (string) ($user->name ?: 'there'),
                code: $code,
                expiresMinutes: self::EXPIRES_MINUTES,
            ));
        } catch (Throwable $e) {
            // Keep the account; drop the unused OTP so Resend is not blocked by cooldown.
            $otp->delete();
            throw $e;
        }
    }

    /**
     * @throws ValidationException
     */
    public function verify(User $user, string $code): void
    {
        if (! $this->needsEmailVerification($user)) {
            return;
        }

        $otp = EmailOtp::query()
            ->where('user_id', $user->id)
            ->whereNull('consumed_at')
            ->orderByDesc('id')
            ->first();

        if (! $otp) {
            throw ValidationException::withMessages([
                'code' => ['No active verification code. Request a new code.'],
            ]);
        }

        if ($otp->expires_at === null || $otp->expires_at->isPast()) {
            $otp->forceFill(['consumed_at' => now()])->save();
            throw ValidationException::withMessages([
                'code' => ['That code has expired. Request a new code.'],
            ]);
        }

        if ((int) $otp->attempts >= self::MAX_ATTEMPTS) {
            $otp->forceFill(['consumed_at' => now()])->save();
            throw ValidationException::withMessages([
                'code' => ['Too many incorrect attempts. Request a new code.'],
            ]);
        }

        $normalized = preg_replace('/\D+/', '', $code) ?? '';
        if (strlen($normalized) !== self::CODE_LENGTH || ! Hash::check($normalized, $otp->code_hash)) {
            $otp->forceFill(['attempts' => (int) $otp->attempts + 1])->save();
            if ((int) $otp->attempts >= self::MAX_ATTEMPTS) {
                $otp->forceFill(['consumed_at' => now()])->save();
                throw ValidationException::withMessages([
                    'code' => ['Too many incorrect attempts. Request a new code.'],
                ]);
            }

            throw ValidationException::withMessages([
                'code' => ['Invalid verification code.'],
            ]);
        }

        $otp->forceFill(['consumed_at' => now()])->save();
        $user->forceFill(['email_verified_at' => now()])->save();
    }

    private function generateCode(): string
    {
        return str_pad((string) random_int(0, 999999), self::CODE_LENGTH, '0', STR_PAD_LEFT);
    }
}
