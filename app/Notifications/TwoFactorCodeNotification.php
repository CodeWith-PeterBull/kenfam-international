<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Delivers the one-time email-OTP challenge code.
 *
 * Queued (CSK benchmark parity) so the login request never blocks on SMTP.
 * Operational implication for local development: with QUEUE_CONNECTION=
 * database a worker must be running for the mail to leave the queue —
 * `composer run dev` starts one; a bare `php artisan serve` does not. With
 * MAIL_MAILER=log the rendered mail (including the code) lands in
 * `storage/logs/laravel.log`. See `.docs/auth-2fa/` for the runbook.
 *
 * A future SMS/other channel should be reflected in via() alongside a
 * `two-factor.channels.*` config flag; the CSK benchmark's dead SMS slot
 * was intentionally not ported.
 */
class TwoFactorCodeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  string  $code  Plaintext OTP — exists only in memory and in
     *                        the rendered mail; storage keeps a keyed digest.
     * @param  int  $expiryMinutes  Validity window communicated to the user.
     */
    public function __construct(
        protected string $code,
        protected int $expiryMinutes,
    ) {}

    /**
     * Delivery channels, gated by two-factor.channels config.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return config('two-factor.channels.mail', true) ? ['mail'] : [];
    }

    /**
     * Build the OTP mail from the markdown template.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Two-Factor Authentication Code - '.config('app.name'))
            ->markdown('emails.two-factor-code', [
                'userName' => $notifiable->name,
                'code' => $this->code,
                'expiryMinutes' => $this->expiryMinutes,
            ]);
    }
}
