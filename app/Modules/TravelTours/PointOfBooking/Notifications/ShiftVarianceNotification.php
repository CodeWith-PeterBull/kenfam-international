<?php

/** Staff alert that a closed booking-desk shift carries a material cash variance. */

declare(strict_types=1);

namespace App\Modules\TravelTours\PointOfBooking\Notifications;

use App\Models\User;
use App\Modules\TravelTours\Notifications\QueuedTravelNotification;
use App\Modules\TravelTours\PointOfBooking\Enums\ShiftStatus;
use App\Modules\TravelTours\PointOfBooking\Models\BookingShift;
use App\Modules\TravelTours\Support\MoneyFormatter;
use Illuminate\Notifications\Messages\MailMessage;

/** Internal notice to the operators who reconcile shifts; it re-reads the shift when it runs. */
final class ShiftVarianceNotification extends QueuedTravelNotification
{
    /** Carry scalars only and apply the module queue policy. */
    public function __construct(
        public readonly string $shiftUlid,
        public readonly int $varianceMinor,
        public readonly int $thresholdMinor,
    ) {
        $this->applyQueuePolicy();
    }

    /** Deliver only while the closed shift still shows the variance that raised the alert. */
    public function shouldSend(object $notifiable, string $channel): bool
    {
        $shift = $this->shift();

        return $channel === 'mail'
            && $shift instanceof BookingShift
            && in_array($shift->status, [ShiftStatus::Closed, ShiftStatus::Reconciled], true)
            && (int) $shift->variance_minor === $this->varianceMinor
            && abs((int) $shift->variance_minor) >= max(1, (int) config('travel-tours.pob.variance_threshold_minor', 10_000));
    }

    /** Build the reconciliation alert without exposing payment detail. */
    public function toMail(object $notifiable): MailMessage
    {
        $shift = $this->shift() ?? BookingShift::query()->where('ulid', $this->shiftUlid)->firstOrFail();
        $direction = $shift->variance_minor < 0 ? 'short' : 'over';
        $exponent = (int) $shift->currency_exponent;
        $greeting = $notifiable instanceof User ? 'Hello '.$notifiable->display_name.',' : 'Hello,';

        return (new MailMessage)
            ->subject("Booking desk variance: {$shift->register->name}")
            ->greeting($greeting)
            ->line("{$shift->register->name} closed {$direction} by ".MoneyFormatter::format(abs((int) $shift->variance_minor), $shift->currency, $exponent).' ('.$shift->operator?->display_name.').')
            ->line('Expected '.MoneyFormatter::format((int) $shift->expected_cash_minor, $shift->currency, $exponent).'; counted '.MoneyFormatter::format((int) $shift->actual_cash_minor, $shift->currency, $exponent).'.')
            ->action('Review booking shifts', route('travel-tours.pob.admin.shifts.index', ['shift-state' => 'closed']))
            ->line('Sign the shift off once the variance has been explained.');
    }

    /** @return array{shift_ulid: string, variance_minor: int, threshold_minor: int} */
    public function toArray(object $notifiable): array
    {
        return ['shift_ulid' => $this->shiftUlid, 'variance_minor' => $this->varianceMinor, 'threshold_minor' => $this->thresholdMinor];
    }

    /** Resolve the current shift without retaining a model in the queue payload. */
    private function shift(): ?BookingShift
    {
        return BookingShift::query()->with(['register', 'operator'])->where('ulid', $this->shiftUlid)->first();
    }
}
