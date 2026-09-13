<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Storefront\Livewire;

use App\Models\User;
use App\Modules\PropertyBooking\Bookings\Enums\BookingPaymentMethod;
use App\Modules\PropertyBooking\Catalog\Models\Property;
use App\Modules\PropertyBooking\Exceptions\PropertyBookingException;
use App\Modules\PropertyBooking\Pricing\Data\BookingQuote;
use App\Modules\PropertyBooking\Storefront\Livewire\Forms\BookingCheckoutForm;
use App\Modules\PropertyBooking\Storefront\Services\BookingAccessUrlService;
use App\Modules\PropertyBooking\Storefront\Services\BookingSelectionSession;
use App\Modules\PropertyBooking\Storefront\Services\StorefrontBookingCheckoutService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

/** Collects public-safe guest data and delegates authoritative placement. */
final class Checkout extends Component
{
    public BookingCheckoutForm $form;

    /** Prefill optional authenticated account values. */
    public function mount(): void
    {
        $account = auth()->user();
        $this->form->fillFromAccount($account instanceof User ? $account : null);
    }

    /** Return a freshly generated quote for the current session selection. */
    #[Computed]
    public function quote(): ?BookingQuote
    {
        try {
            return app(BookingSelectionSession::class)->current();
        } catch (PropertyBookingException $exception) {
            $this->addError('checkout', $exception->getMessage());

            return null;
        }
    }

    /** Return configured public payment preferences backed by known enums. */
    #[Computed]
    public function paymentMethods(): array
    {
        return collect(config('property-booking.storefront.payment_methods', []))
            ->map(static fn (mixed $method): ?BookingPaymentMethod => is_string($method) ? BookingPaymentMethod::tryFrom($method) : null)
            ->filter()
            ->values()
            ->all();
    }

    /** Return configured identity document choices for the public form. */
    #[Computed]
    public function identityTypes(): array
    {
        return collect(config('property-booking.identity.types', []))
            ->filter(static fn (mixed $label, mixed $value): bool => is_string($value) && $value !== '' && is_string($label) && $label !== '')
            ->all();
    }

    /** Determine whether public checkout requires a guest identity document. */
    #[Computed]
    public function guestIdentityRequired(): bool
    {
        return (bool) config('property-booking.storefront.guest_identity_required', false);
    }

    /** Re-quote and atomically place the selected stay. */
    public function place(
        StorefrontBookingCheckoutService $checkout,
        BookingSelectionSession $selection,
        BookingAccessUrlService $urls,
    ): void {
        $this->resetErrorBag('checkout');
        $this->form->validate();
        $selected = $selection->selection();
        if ($selected === null) {
            $this->addError('checkout', 'Your stay selection expired. Choose an available stay again.');

            return;
        }

        try {
            $account = auth()->user();
            $booking = $checkout->place(
                selection: $selected,
                guestData: $this->form->guestData(),
                paymentPreference: $this->form->payment(),
                specialRequests: trim($this->form->specialRequests) ?: null,
                account: $account instanceof User ? $account : null,
            );
        } catch (PropertyBookingException $exception) {
            unset($this->quote);
            $this->addError('checkout', $exception->getMessage());

            return;
        }

        $selection->clear();
        $this->redirect($urls->confirmation($booking));
    }

    /** Render the guest checkout form and server-owned price review. */
    public function render(): View
    {
        $quote = $this->quote;
        $property = $quote instanceof BookingQuote
            ? Property::query()->published()->find($quote->propertyId)
            : null;

        if ($quote instanceof BookingQuote && ! $property instanceof Property) {
            $quote = null;
            $this->addError('checkout', 'The selected property is no longer available.');
        }

        return view('property-booking::livewire.storefront.checkout', compact('property', 'quote'));
    }
}
