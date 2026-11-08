<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $recipientName,
        public readonly string $code,
        public readonly int $expiresMinutes = 10,
    ) {
    }

    public function build(): self
    {
        $minutes = $this->expiresMinutes;

        return $this->subject('Your OJT Intern Path verification code')
            ->html(<<<HTML
                <div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:24px;color:#0f172a;">
                  <h2 style="margin:0 0 16px;">Verify your email</h2>
                  <p style="margin:0 0 12px;">Hello {$this->recipientName},</p>
                  <p style="margin:0 0 12px;">Use this one-time code to verify your organization account email before uploading verification documents:</p>
                  <p style="margin:24px 0;font-size:28px;font-weight:700;letter-spacing:0.2em;">{$this->code}</p>
                  <p style="margin:0 0 12px;font-size:14px;color:#475569;">This code expires in {$minutes} minutes and can be used once.</p>
                  <p style="margin:24px 0 0;font-size:13px;color:#64748b;">If you did not create this account, you can ignore this email.</p>
                </div>
            HTML);
    }
}
