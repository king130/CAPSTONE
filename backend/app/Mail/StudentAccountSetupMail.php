<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class StudentAccountSetupMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $studentName,
        public readonly string $schoolName,
        public readonly string $setupUrl,
        public readonly ?string $senderEmail = null,
        public readonly ?string $senderName = null,
        public readonly bool $useSenderAsFrom = false,
    ) {
    }

    public function build(): self
    {
        $mail = $this->subject('Set up your student account');

        if ($this->senderEmail) {
            if ($this->useSenderAsFrom) {
                $mail->from($this->senderEmail, $this->senderName ?: $this->schoolName);
            }

            $mail->replyTo($this->senderEmail, $this->senderName ?: $this->schoolName);
        }

        return $mail->html(<<<HTML
                <div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:24px;color:#0f172a;">
                  <h2 style="margin:0 0 16px;">Complete your student account setup</h2>
                  <p style="margin:0 0 12px;">Hello {$this->studentName},</p>
                  <p style="margin:0 0 12px;">{$this->schoolName} created your student account. Use the button below to create your password and confirm your account.</p>
                  <p style="margin:24px 0;">
                    <a href="{$this->setupUrl}" style="display:inline-block;background:#2563eb;color:#ffffff;text-decoration:none;padding:12px 18px;border-radius:10px;font-weight:700;">Create Password</a>
                  </p>
                  <p style="margin:0 0 12px;font-size:14px;color:#475569;">If the button does not work, open this link in your browser:</p>
                  <p style="margin:0;font-size:14px;word-break:break-all;"><a href="{$this->setupUrl}">{$this->setupUrl}</a></p>
                  <p style="margin:24px 0 0;font-size:13px;color:#64748b;">This link expires in 48 hours. If it expires, ask your school admin to send a new one.</p>
                </div>
            HTML);
    }
}
