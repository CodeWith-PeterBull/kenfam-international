<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\PointOfBooking\Notifications;

use App\Contracts\ResolvesInstitutionProfile;
use App\Models\User;
use App\Modules\PropertyBooking\Notifications\QueuedPropertyBookingNotification;
use App\Modules\PropertyBooking\PointOfBooking\Enums\ReceptionShiftStatus;
use App\Modules\PropertyBooking\PointOfBooking\Models\ReceptionShift;
use App\Modules\PropertyBooking\Support\MoneyFormatter;
use App\Modules\PropertyBooking\Support\PropertyBookingPermission;
use Illuminate\Notifications\Messages\MailMessage;

/** Alerts scoped shift managers about a material closed-shift variance. */
final class ReceptionShiftVarianceNotification extends QueuedPropertyBookingNotification
{
    protected const EVENT_KEY = 'reception_shift_variance';

    /** Create a scalar-only material variance alert. */
    public function __construct(
        public readonly string $shiftUlid,
        public readonly int $propertyId,
        public readonly int $varianceMinor,
        public readonly int $thresholdMinor,
    ) {
        parent::__construct();
    }

    /** Revalidate the closed shift, current threshold, permission, and scope. */
    public function shouldSend(object $notifiable, string $channel): bool
    {
        $shift = $this->shift();
        $configuredThreshold = max(1, (int) config('property-booking.pob.shift_variance_threshold_minor', 10_000));

        return $channel === 'mail'
            && $this->permittedStaff($notifiable, PropertyBookingPermission::MANAGE_SHIFTS, $this->propertyId)
            && $shift?->property_id === $this->propertyId
            && $shift->status === ReceptionShiftStatus::Closed
            && $shift->variance_minor === $this->varianceMinor
            && abs($shift->variance_minor) >= $configuredThreshold;
    }

    /** Build the reconciliation alert without exposing payment metadata. */
    public function toMail(object $notifiable): MailMessage
    {
        $shift = $this->shiftOrFail();
        $profile = app(ResolvesInstitutionProfile::class)->current();
        $direction = $shift->variance_minor < 0 ? 'short' : 'over';
        $greeting = $notifiable instanceof User ? 'Hello '.$notifiable->display_name.',' : 'Hello,';

        return (new MailMessage)
            ->subject("{$profile->shortName} reception variance: {$shift->register->name}")
            ->greeting($greeting)
            ->line("{$shift->register->name} at {$shift->property->name} closed {$direction} by "
                .MoneyFormatter::format(abs((int) $shift->variance_minor), $shift->currency).'.')
            ->line('Expected '.MoneyFormatter::format((int) $shift->expected_cash_minor, $shift->currency)
                .'; counted '.MoneyFormatter::format((int) $shift->counted_cash_minor, $shift->currency).'.')
            ->action('Review reception shift', route('property-booking.pob.admin.shifts.index'))
            ->salutation("Regards,\n{$profile->shortName}");
    }

    /** @return array{shift_ulid: string, property_id: int, variance_minor: int, threshold_minor: int} */
    public function toArray(object $notifiable): array
    {
        return [
            'shift_ulid' => $this->shiftUlid,
            'property_id' => $this->propertyId,
            'variance_minor' => $this->varianceMinor,
            'threshold_minor' => $this->thresholdMinor,
        ];
    }

    /** Resolve the current shift without retaining a model in the queue payload. */
    private function shift(): ?ReceptionShift
    {
        return ReceptionShift::query()
            ->with(['property', 'register'])
            ->where('ulid', $this->shiftUlid)
            ->first();
    }

    /** Resolve the current shift or fail the queue job for retry. */
    private function shiftOrFail(): ReceptionShift
    {
        return ReceptionShift::query()
            ->with(['property', 'register'])
            ->where('ulid', $this->shiftUlid)
            ->firstOrFail();
    }
}
