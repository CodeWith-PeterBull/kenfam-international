<?php

/** Public checkout for one active availability hold owned by the visitor's session. */

declare(strict_types=1);

namespace App\Modules\TravelTours\Storefront\Livewire;

use App\Modules\TravelTours\Bookings\Exceptions\AvailabilityException;
use App\Modules\TravelTours\Bookings\Exceptions\DuplicateOperationConflict;
use App\Modules\TravelTours\Bookings\Services\TourBookingService;
use App\Modules\TravelTours\Customers\Exceptions\CustomerIdentityConflict;
use App\Modules\TravelTours\Scheduling\Enums\HoldStatus;
use App\Modules\TravelTours\Scheduling\Exceptions\HoldOwnershipMismatch;
use App\Modules\TravelTours\Scheduling\Models\AvailabilityHold;
use App\Modules\TravelTours\Scheduling\Services\AvailabilityHoldService;
use App\Modules\TravelTours\Storefront\Livewire\Forms\CheckoutForm;
use App\Modules\TravelTours\Storefront\Services\BookingAccessUrlService;
use App\Modules\TravelTours\Storefront\Services\CheckoutSession;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Collect the customer, one identity per held seat, a payment preference, and
 * terms acceptance, then place the booking through TourBookingService. No
 * payment is taken here: the preference only tells the travel desk how the
 * customer intends to settle, and the desk records and confirms the money.
 */
final class BookingCheckout extends Component
{
    public CheckoutForm $form;

    #[Locked]
    public int $holdId;

    /** Bind the checkout to a hold this session owns and seed one row per traveller. */
    public function mount(AvailabilityHold $hold, AvailabilityHoldService $holds, CheckoutSession $checkout): void
    {
        $this->holdId = $hold->getKey();
        $this->assertOwned($holds, $checkout);
        $this->form->start($this->hold);
    }

    /** Ownership is proven again on every request; the hold id alone is never trusted. */
    public function hydrate(AvailabilityHoldService $holds, CheckoutSession $checkout): void
    {
        $this->assertOwned($holds, $checkout);
    }

    /**
     * Validate each text field as it loses focus so stale messages never sit
     * beside corrected input; the lead toggle instead clears traveller 1's messages.
     */
    public function updated(string $property): void
    {
        if ($property === 'form.leadTravels') {
            $this->resetErrorBag(['form.participants.0.first_name', 'form.participants.0.last_name']);

            return;
        }
        if (str_starts_with($property, 'form.')) {
            $this->form->validateOnly(substr($property, 5));
        }
    }

    /** The held quote with its departure and tour. */
    #[Computed]
    public function hold(): AvailabilityHold
    {
        return AvailabilityHold::query()->with('departure.tour')->findOrFail($this->holdId);
    }

    /** Whether the hold can still be converted into a booking. */
    public function holdIsActive(): bool
    {
        $hold = $this->hold;

        return $hold->status === HoldStatus::Active && $hold->expires_at->isFuture();
    }

    /**
     * Place the booking and continue to its private confirmation page.
     *
     * Every domain refusal (expired hold, changed price, participant mix,
     * conflicting customer identity) is shown inline so the visitor can act.
     */
    public function place(TourBookingService $bookings, CheckoutSession $checkout, BookingAccessUrlService $urls): void
    {
        $this->form->validate();
        $hold = $this->hold;

        try {
            $booking = $bookings->place($this->form->toPlacement($hold, $checkout->ownerToken(), $hold->departure->tour->policy_version ?? 'tour-terms'));
        } catch (AvailabilityException|HoldOwnershipMismatch|CustomerIdentityConflict|DuplicateOperationConflict|InvalidArgumentException $exception) {
            $this->addError('checkout', $exception->getMessage());

            return;
        }

        $checkout->rotate();
        $this->redirect($urls->confirmation($booking));
    }

    /** Render the checkout with the hold, its quote snapshot, and the allowed payment methods. */
    public function render(): View
    {
        return view('travel-tours::livewire.storefront.booking-checkout', [
            'hold' => $this->hold,
            'methods' => CheckoutForm::allowedMethods(),
        ]);
    }

    /** Refuse any session that cannot prove it created the hold. */
    private function assertOwned(AvailabilityHoldService $holds, CheckoutSession $checkout): void
    {
        try {
            $holds->assertOwnedBy($this->hold, $checkout->ownerToken(), null);
        } catch (HoldOwnershipMismatch) {
            abort(403);
        }
    }
}
