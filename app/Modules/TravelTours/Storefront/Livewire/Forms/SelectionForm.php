<?php

/** Validates the public participant mix and promotion code for a departure quote. */

declare(strict_types=1);

namespace App\Modules\TravelTours\Storefront\Livewire\Forms;

use App\Modules\TravelTours\Pricing\Data\ParticipantMix;
use App\Modules\TravelTours\Pricing\Data\TourQuoteRequest;
use Closure;
use Livewire\Form;

/** Keep participant counts within the configured booking limit before any quote is requested. */
final class SelectionForm extends Form
{
    public string $adults = '1';

    public string $children = '0';

    public string $infants = '0';

    public string $promotionCode = '';

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        $maximum = $this->maximumParticipants();

        return [
            'adults' => ['required', 'integer', 'min:1', 'max:'.$maximum, $this->totalWithinLimit($maximum)],
            'children' => ['required', 'integer', 'min:0', 'max:'.$maximum],
            'infants' => ['required', 'integer', 'min:0', 'max:'.$maximum],
            'promotionCode' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9-]+$/'],
        ];
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return [
            'adults.required' => 'At least one adult travels on every booking.',
            'adults.min' => 'At least one adult travels on every booking.',
            'children.required' => 'Enter 0 when no children travel.',
            'infants.required' => 'Enter 0 when no infants travel.',
            'promotionCode.regex' => 'Promotion codes use letters, numbers, and hyphens only.',
        ];
    }

    /** Return the validated participant counts as the shared pricing input. */
    public function mix(): ParticipantMix
    {
        return new ParticipantMix((int) $this->adults, (int) $this->children, (int) $this->infants);
    }

    /** Build the server-verifiable quote request for one departure and rate plan. */
    public function toRequest(int $departureId, int $ratePlanId): TourQuoteRequest
    {
        $code = strtoupper(trim($this->promotionCode));

        return new TourQuoteRequest($departureId, $ratePlanId, $this->mix(), $code === '' ? null : $code);
    }

    /** Return the total number of people currently entered, treating blanks as zero. */
    public function participants(): int
    {
        return (int) $this->adults + (int) $this->children + (int) $this->infants;
    }

    /** Return the configured ceiling shared with the pricing input object. */
    public function maximumParticipants(): int
    {
        return max(1, (int) config('travel-tours.booking.maximum_participants', 20));
    }

    /** Reject a mix whose total exceeds the booking ceiling even when each count is valid alone. */
    private function totalWithinLimit(int $maximum): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($maximum): void {
            if ($this->participants() > $maximum) {
                $fail("A booking cannot exceed {$maximum} participants in total.");
            }
        };
    }
}
