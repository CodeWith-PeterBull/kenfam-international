<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Bookings\Livewire\Forms;

use App\Modules\PropertyBooking\Bookings\Data\BookingModificationData;
use App\Modules\PropertyBooking\Bookings\Models\Booking;
use Carbon\CarbonImmutable;
use Livewire\Form;

/** Validates operator-entered local arrival and departure changes. */
final class BookingModificationForm extends Form
{
    public string $startsAt = '';

    public string $endsAt = '';

    public string $reason = '';

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'startsAt' => ['required', 'date_format:Y-m-d\TH:i'],
            'endsAt' => ['required', 'date_format:Y-m-d\TH:i', 'after:startsAt'],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }

    /** Populate property-local editable values from one booking. */
    public function fillFromBooking(Booking $booking): void
    {
        $this->startsAt = $booking->starts_at->timezone($booking->property_timezone)->format('Y-m-d\TH:i');
        $this->endsAt = $booking->ends_at->timezone($booking->property_timezone)->format('Y-m-d\TH:i');
        $this->reason = '';
        $this->resetValidation();
    }

    /** Build the UTC domain request after form validation. */
    public function data(Booking $booking): BookingModificationData
    {
        return new BookingModificationData(
            CarbonImmutable::createFromFormat('Y-m-d\TH:i', $this->startsAt, $booking->property_timezone)->utc(),
            CarbonImmutable::createFromFormat('Y-m-d\TH:i', $this->endsAt, $booking->property_timezone)->utc(),
            trim($this->reason),
        );
    }
}
