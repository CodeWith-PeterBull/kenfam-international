<?php

/** Livewire validation and hydration for the departure operator form. */

declare(strict_types=1);

namespace App\Modules\TravelTours\Scheduling\Livewire\Forms;

use App\Modules\TravelTours\Bookings\Enums\BookingMode;
use App\Modules\TravelTours\Scheduling\Data\DepartureData;
use App\Modules\TravelTours\Scheduling\Models\TourDeparture;
use Livewire\Form;

/** Map local wall-time input to a typed service request. */
final class DepartureForm extends Form
{
    public string $tourId = '';

    public string $code = '';

    public string $timezone = '';

    public string $localStart = '';

    public string $localEnd = '';

    public string $localBookingOpen = '';

    public string $localBookingClose = '';

    public string $capacity = '24';

    public string $minimumParticipants = '1';

    public string $ratePlanId = '';

    public string $bookingMode = '';

    public string $meetingInstructions = '';

    public string $operationalNotes = '';

    /** Validate browser inputs before resolving domain relationships. */
    protected function rules(): array
    {
        return [
            'tourId' => ['required', 'integer', 'exists:travel_tours,id'],
            'code' => ['required', 'regex:/^[a-zA-Z0-9][a-zA-Z0-9-]{2,79}$/'],
            'timezone' => ['required', 'timezone', 'max:64'],
            'localStart' => ['required', 'date_format:Y-m-d\TH:i'],
            'localEnd' => ['required', 'date_format:Y-m-d\TH:i'],
            'localBookingOpen' => ['nullable', 'date_format:Y-m-d\TH:i'],
            'localBookingClose' => ['nullable', 'date_format:Y-m-d\TH:i'],
            'capacity' => ['required', 'integer', 'min:1', 'max:100000'],
            'minimumParticipants' => ['required', 'integer', 'min:1', 'max:100000'],
            'ratePlanId' => ['nullable', 'integer', 'exists:travel_tour_rate_plans,id'],
            'bookingMode' => ['nullable', 'in:instant,approval'],
            'meetingInstructions' => ['nullable', 'string', 'max:5000'],
            'operationalNotes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /** Start an empty draft, optionally locked to one tour. */
    public function start(?int $tourId): void
    {
        $this->reset();
        $this->tourId = $tourId ? (string) $tourId : '';
        $this->timezone = (string) config('travel-tours.defaults.timezone', 'UTC');
        $this->capacity = '24';
        $this->minimumParticipants = '1';
    }

    /** Display persisted UTC instants in the departure's own local timezone. */
    public function fillFromDeparture(TourDeparture $departure): void
    {
        $this->tourId = (string) $departure->tour_id;
        $this->code = $departure->code;
        $this->timezone = $departure->timezone;
        $this->localStart = $departure->starts_at->copy()->timezone($departure->timezone)->format('Y-m-d\TH:i');
        $this->localEnd = $departure->ends_at->copy()->timezone($departure->timezone)->format('Y-m-d\TH:i');
        $this->localBookingOpen = $departure->booking_opens_at?->copy()->timezone($departure->timezone)->format('Y-m-d\TH:i') ?? '';
        $this->localBookingClose = $departure->booking_closes_at?->copy()->timezone($departure->timezone)->format('Y-m-d\TH:i') ?? '';
        $this->capacity = (string) $departure->capacity;
        $this->minimumParticipants = (string) $departure->minimum_participants;
        $this->ratePlanId = $departure->rate_plan_id ? (string) $departure->rate_plan_id : '';
        $this->bookingMode = $departure->booking_mode?->value ?? '';
        $this->meetingInstructions = $departure->meeting_instructions ?? '';
        $this->operationalNotes = $departure->operational_notes ?? '';
    }

    /** Convert only validated user-editable fields to the service contract. */
    public function toData(): DepartureData
    {
        return new DepartureData(
            (int) $this->tourId, trim($this->code), trim($this->timezone), $this->localStart, $this->localEnd,
            $this->localBookingOpen ?: null, $this->localBookingClose ?: null,
            (int) $this->capacity, (int) $this->minimumParticipants,
            $this->ratePlanId !== '' ? (int) $this->ratePlanId : null,
            $this->bookingMode !== '' ? BookingMode::from($this->bookingMode) : null,
            trim($this->meetingInstructions) ?: null, trim($this->operationalNotes) ?: null,
        );
    }
}
