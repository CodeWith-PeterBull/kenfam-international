<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Storefront\Livewire;

use App\Models\User;
use App\Modules\Commerce\Exceptions\CommerceException;
use App\Modules\Commerce\Orders\Data\OrderPlacementData;
use App\Modules\Commerce\Orders\Enums\FulfillmentType;
use App\Modules\Commerce\Orders\Enums\PaymentMethod;
use App\Modules\Commerce\Storefront\Data\StorefrontCartSnapshot;
use App\Modules\Commerce\Storefront\Livewire\Forms\CheckoutForm;
use App\Modules\Commerce\Storefront\Services\CartSessionService;
use App\Modules\Commerce\Storefront\Services\OrderAccessUrlService;
use App\Modules\Commerce\Storefront\Services\StorefrontCheckoutService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Collects guest checkout data and delegates all trusted writes to services.
 */
final class Checkout extends Component
{
    public CheckoutForm $form;

    public function mount(): void
    {
        $user = auth()->user();
        $this->form->fillFromAccount($user instanceof User ? $user : null);
    }

    public function updatedFormFulfillmentType(): void
    {
        unset($this->snapshot);
    }

    #[Computed]
    public function snapshot(): StorefrontCartSnapshot
    {
        $fulfillment = FulfillmentType::tryFrom($this->form->fulfillmentType) ?? FulfillmentType::Pickup;

        return app(CartSessionService::class)->snapshot($fulfillment);
    }

    /** @return list<PaymentMethod> */
    #[Computed]
    public function paymentMethods(): array
    {
        return collect(config('commerce.checkout.payment_methods', []))
            ->map(static fn (mixed $method): ?PaymentMethod => is_string($method) ? PaymentMethod::tryFrom($method) : null)
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Place the order without accepting any client-owned monetary values.
     */
    public function place(
        StorefrontCheckoutService $checkout,
        CartSessionService $cart,
        OrderAccessUrlService $urls,
    ): void {
        $this->resetErrorBag('checkout');
        $this->form->validate();
        unset($this->snapshot);
        $snapshot = $this->snapshot;

        if (! $snapshot->canCheckout()) {
            $this->addError('checkout', $snapshot->issues[0] ?? 'Your cart is not ready for checkout.');

            return;
        }

        try {
            $data = new OrderPlacementData(
                items: $snapshot->placementItems(),
                customer: $this->form->customerSnapshot(),
                fulfillmentType: $this->form->fulfillment(),
                preferredPaymentMethod: $this->form->payment(),
                deliveryFeeMinor: $cart->deliveryFee($this->form->fulfillment()),
                customerNote: trim($this->form->customerNote) ?: null,
            );
            $account = auth()->user();
            $order = $checkout->place($data, $account instanceof User ? $account : null);
        } catch (CommerceException $exception) {
            $this->addError('checkout', $exception->getMessage());

            return;
        }

        $cart->clear();
        $this->dispatch('commerce-cart-updated');

        $this->redirect($urls->confirmation($order));
    }

    public function render(): View
    {
        return view('commerce::livewire.storefront.checkout');
    }
}
