<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Storefront\Livewire;

use App\Modules\Commerce\Exceptions\InvalidCartException;
use App\Modules\Commerce\Storefront\Services\CartSessionService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Adds one server-identified product to the shared session cart.
 */
final class AddToCart extends Component
{
    #[Locked]
    public int $productId;

    #[Locked]
    public int $minimum = 1;

    #[Locked]
    public ?int $maximum = null;

    #[Locked]
    public bool $compact = true;

    public int $quantity = 1;

    public string $feedback = '';

    /**
     * Initialize immutable product constraints supplied by the server view.
     */
    public function mount(
        int $productId,
        int $minimum = 1,
        ?int $maximum = null,
        bool $compact = true,
    ): void {
        $this->productId = $productId;
        $this->minimum = max(1, $minimum);
        $this->maximum = $maximum;
        $this->compact = $compact;
        $this->quantity = $this->minimum;
    }

    public function increment(): void
    {
        $maximum = $this->maximum ?? 1_000_000;
        $this->quantity = min($maximum, $this->quantity + 1);
    }

    public function decrement(): void
    {
        $this->quantity = max($this->minimum, $this->quantity - 1);
    }

    /**
     * Revalidate current product state and persist only its ID and quantity.
     */
    public function add(CartSessionService $cart): void
    {
        $this->feedback = '';

        try {
            $snapshot = $cart->add($this->productId, $this->quantity);
        } catch (InvalidCartException $exception) {
            $this->addError('cart', $exception->getMessage());

            return;
        }

        $this->resetErrorBag('cart');
        $this->feedback = "Added to cart. {$snapshot->itemCount} items ready.";
        $this->quantity = $this->minimum;
        $this->dispatch('commerce-cart-updated');
    }

    public function render(): View
    {
        return view('commerce::livewire.storefront.add-to-cart');
    }
}
