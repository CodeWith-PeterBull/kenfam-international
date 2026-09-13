<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Notifications;

use App\Contracts\ResolvesInstitutionProfile;
use App\Modules\PropertyBooking\Bookings\Models\Booking;
use App\Modules\PropertyBooking\Storefront\Services\BookingAccessUrlService;
use App\Modules\PropertyBooking\Support\MoneyFormatter;
use Illuminate\Notifications\Messages\MailMessage;

/** Builds one privacy-safe customer message from a current booking snapshot. */
abstract class QueuedBookingCustomerNotification extends QueuedPropertyBookingNotification
{
    /** Create a scalar-only queued notification. */
    public function __construct(public string $bookingUlid)
    {
        parent::__construct();
    }

    /** Revalidate the booking state and immutable email route before delivery. */
    final public function shouldSend(object $notifiable, string $channel): bool
    {
        $booking = $this->booking();

        return $channel === 'mail'
            && $booking instanceof Booking
            && $this->matchesBookingEmail($notifiable, $booking->guest_email)
            && $this->bookingMatches($booking);
    }

    /** Build the dedicated customer message without exposing private fields. */
    final public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->bookingOrFail();
        $profile = app(ResolvesInstitutionProfile::class)->current();
        $message = (new MailMessage)
            ->subject($this->subject($booking, $profile->shortName))
            ->greeting('Hello '.$this->guestName($booking).',');

        foreach ($this->lines($booking) as $line) {
            $message->line($line);
        }

        $message->action($this->actionLabel(), app(BookingAccessUrlService::class)->tracking($booking));
        if ($this->includeDocumentLink()) {
            $message->line('[Download your printable booking summary]('.app(BookingAccessUrlService::class)->document($booking).')');
        }

        return $message
            ->line('Keep this message private because its links provide temporary access to your booking details.')
            ->salutation("Regards,\n{$profile->shortName}");
    }

    /** @return array{booking_ulid: string} */
    public function toArray(object $notifiable): array
    {
        return ['booking_ulid' => $this->bookingUlid];
    }

    /** Determine whether current aggregate state still represents this event. */
    abstract protected function bookingMatches(Booking $booking): bool;

    /** Build the event-specific subject. */
    abstract protected function subject(Booking $booking, string $institutionName): string;

    /** @return list<string> */
    abstract protected function lines(Booking $booking): array;

    /** Label the signed tracking action. */
    protected function actionLabel(): string
    {
        return 'View booking';
    }

    /** Include a signed printable summary link where useful. */
    protected function includeDocumentLink(): bool
    {
        return true;
    }

    /** Format the immutable stay interval in the property's snapshotted timezone. */
    protected function stayLine(Booking $booking): string
    {
        return 'Stay: '.$booking->starts_at->timezone($booking->property_timezone)->format('d M Y, H:i')
            .' to '.$booking->ends_at->timezone($booking->property_timezone)->format('d M Y, H:i').'.';
    }

    /** Format the current paid and outstanding values. */
    protected function settlementLine(Booking $booking): string
    {
        return 'Paid: '.MoneyFormatter::format((int) $booking->paid_minor, $booking->currency)
            .'. Balance: '.MoneyFormatter::format((int) $booking->balance_minor, $booking->currency).'.';
    }

    /** Resolve the current aggregate with optional event-specific relationships. */
    protected function booking(): ?Booking
    {
        return Booking::query()
            ->with($this->relations())
            ->where('ulid', $this->bookingUlid)
            ->first();
    }

    /** Resolve the current aggregate or fail the queued job for retry. */
    protected function bookingOrFail(): Booking
    {
        return Booking::query()
            ->with($this->relations())
            ->where('ulid', $this->bookingUlid)
            ->firstOrFail();
    }

    /** @return list<string> */
    protected function relations(): array
    {
        return [];
    }

    /** Resolve a bounded greeting from immutable name snapshots. */
    private function guestName(Booking $booking): string
    {
        return trim((string) $booking->guest_first_name) !== '' ? $booking->guest_first_name : 'Guest';
    }
}
