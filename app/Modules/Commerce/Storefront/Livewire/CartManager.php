<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Storefront\Livewire;

use App\Modules\Commerce\Exceptions\InvalidCartException;
use App\Modules\Commerce\Storefront\Data\StorefrontCartSnapshot;
use App\Modules\Commerce\Storefront\Services\CartSessionService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Presents and mutates the current session cart through server-owned rules.
 */
final class CartManager extends Component
{
    public string $feedback = '';

    #[Computed]
    public function snapshot(): StorefrontCartSnapshot
    {
        return app(CartSessionService::class)->snapshot();
    }

    public function increment(int $productId, CartSessionService $cart): void
    {
        $line = collect($this->snapshot->calculation->lines)
            ->first(static fn ($candidate): bool => $candidate->product->getKey() === $productId);
        abort_if($line === null, 404);
        $this->changeQuantity($cart, $productId, $line->quantity + 1);
    }

    public function decrement(int $productId, CartSessionService $cart): void
    {
        $line = collect($this->snapshot->calculation->lines)
            ->first(static fn ($candidate): bool => $candidate->product->getKey() === $productId);
        abort_if($line === null, 404);
        $this->changeQuantity($cart, $productId, $line->quantity - 1);
    }

    public function remove(int $productId, CartSessionService $cart): void
    {
        $cart->remove($productId);
        $this->feedback = 'Product removed from your cart.';
        $this->afterMutation();
    }

    public function clear(CartSessionService $cart): void
    {
        $cart->clear();
        $this->feedback = 'Your cart is now empty.';
        $this->afterMutation();
    }

    public function render(): View
    {
        return view('commerce::livewire.storefront.cart-manager');
    }

    private function changeQuantity(CartSessionService $cart, int $productId, int $quantity): void
    {
        try {
            $cart->setQuantity($productId, $quantity);
        } catch (InvalidCartException $exception) {
            $this->addError('cart', $exception->getMessage());

            return;
        }

        $this->feedback = 'Cart quantity updated.';
        $this->afterMutation();
    }

    private function afterMutation(): void
    {
        unset($this->snapshot);
        $this->resetErrorBag('cart');
        $this->dispatch('commerce-cart-updated');
    }
}
