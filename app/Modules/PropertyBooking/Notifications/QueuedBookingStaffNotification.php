<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Notifications;

use App\Contracts\ResolvesInstitutionProfile;
use App\Models\User;
use App\Modules\PropertyBooking\Bookings\Models\Booking;
use Illuminate\Notifications\Messages\MailMessage;

/** Builds one property-scoped operational message from a current booking. */
abstract class QueuedBookingStaffNotification extends QueuedPropertyBookingNotification
{
    /** Create a scalar-only staff notification. */
    public function __construct(
        public string $bookingUlid,
        public int $propertyId,
    ) {
        parent::__construct();
    }

    /** Revalidate permission, property scope, and current aggregate state. */
    final public function shouldSend(object $notifiable, string $channel): bool
    {
        $booking = $this->booking();

        return $channel === 'mail'
            && $booking instanceof Booking
            && $booking->property_id === $this->propertyId
            && $this->permittedStaff($notifiable, $this->permission(), $this->propertyId)
            && $this->bookingMatches($booking);
    }

    /** Build a concise staff message with an authorized administration link. */
    final public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->bookingOrFail();
        $profile = app(ResolvesInstitutionProfile::class)->current();
        $greeting = $notifiable instanceof User ? 'Hello '.$notifiable->display_name.',' : 'Hello,';
        $message = (new MailMessage)
            ->subject($this->subject($booking, $profile->shortName))
            ->greeting($greeting);

        foreach ($this->lines($booking) as $line) {
            $message->line($line);
        }

        return $message
            ->action('Review booking', route('property-booking.admin.bookings.index', [
                'booking-q' => $booking->booking_number,
            ]))
            ->salutation("Regards,\n{$profile->shortName}");
    }

    /** @return array{booking_ulid: string, property_id: int} */
    public function toArray(object $notifiable): array
    {
        return ['booking_ulid' => $this->bookingUlid, 'property_id' => $this->propertyId];
    }

    /** Return the capability required by this staff message. */
    abstract protected function permission(): string;

    /** Determine whether current aggregate state still represents this event. */
    abstract protected function bookingMatches(Booking $booking): bool;

    /** Build the event-specific subject. */
    abstract protected function subject(Booking $booking, string $institutionName): string;

    /** @return list<string> */
    abstract protected function lines(Booking $booking): array;

    /** Resolve the current aggregate with optional event-specific relationships. */
    protected function booking(): ?Booking
    {
        return Booking::query()->with($this->relations())->where('ulid', $this->bookingUlid)->first();
    }

    /** Resolve the current aggregate or fail the queued job for retry. */
    protected function bookingOrFail(): Booking
    {
        return Booking::query()->with($this->relations())->where('ulid', $this->bookingUlid)->firstOrFail();
    }

    /** @return list<string> */
    protected function relations(): array
    {
        return [];
    }
}
