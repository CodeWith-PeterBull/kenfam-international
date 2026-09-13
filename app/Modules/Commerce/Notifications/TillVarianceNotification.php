<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Notifications;

use App\Contracts\ResolvesInstitutionProfile;
use App\Modules\Commerce\PointOfSale\Enums\TillSessionStatus;
use App\Modules\Commerce\PointOfSale\Models\TillSession;
use App\Modules\Commerce\Support\CommercePermission;
use App\Modules\Commerce\Support\MoneyFormatter;
use Illuminate\Notifications\Messages\MailMessage;

/** Alerts till managers about one materially different closing cash count. */
final class TillVarianceNotification extends QueuedCommerceNotification
{
    protected const EVENT_KEY = 'till_variance';

    public function __construct(
        public readonly string $tillSessionUlid,
        public readonly int $varianceMinor,
        public readonly int $thresholdMinor,
    ) {
        parent::__construct();
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        $session = $this->session();
        $configuredThreshold = max(1, (int) config('commerce.notifications.till_variance_threshold_minor', 10_000));

        return $channel === 'mail'
            && $this->permittedStaff($notifiable, CommercePermission::MANAGE_TILLS)
            && $session?->status === TillSessionStatus::Closed
            && $session->variance_minor === $this->varianceMinor
            && abs($session->variance_minor) >= $configuredThreshold;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $session = $this->sessionOrFail();
        $profile = app(ResolvesInstitutionProfile::class)->current();
        $direction = $session->variance_minor < 0 ? 'short' : 'over';

        return (new MailMessage)
            ->subject("{$profile->shortName} till variance: {$session->register->name}")
            ->greeting('Till variance requires review')
            ->line("{$session->register->name} closed {$direction} by ".MoneyFormatter::format(abs($session->variance_minor)).'.')
            ->line('Expected '.MoneyFormatter::format($session->expected_cash_minor).'; counted '.MoneyFormatter::format((int) $session->counted_cash_minor).'.')
            ->action('Review till session', route('commerce.pos.admin.tills.index'))
            ->salutation("Regards,\n{$profile->shortName}");
    }

    /** @return array{till_session_ulid: string, variance_minor: int, threshold_minor: int} */
    public function toArray(object $notifiable): array
    {
        return [
            'till_session_ulid' => $this->tillSessionUlid,
            'variance_minor' => $this->varianceMinor,
            'threshold_minor' => $this->thresholdMinor,
        ];
    }

    private function session(): ?TillSession
    {
        return TillSession::query()->with('register')->where('ulid', $this->tillSessionUlid)->first();
    }

    private function sessionOrFail(): TillSession
    {
        return TillSession::query()->with('register')->where('ulid', $this->tillSessionUlid)->firstOrFail();
    }
}
