<?php

namespace App\Notifications;

use App\Contracts\ResolvesInstitutionProfile;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SystemTestMailNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly string $recipientName = 'Administrator') {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $profile = app(ResolvesInstitutionProfile::class)->current();

        return (new MailMessage)
            ->subject("{$profile->shortName} communication template test")
            ->greeting("Hello {$this->recipientName},")
            ->line('This message verifies the shared Aureon notification template and Institution Details integration.')
            ->line('Future CMS notifications inherit this organization identity, contact footer, and visual treatment automatically.')
            ->action('Open administration', route('admin.dashboard'))
            ->line('No action is required for this test message.')
            ->salutation("Regards,\n{$profile->shortName}");
    }
}
