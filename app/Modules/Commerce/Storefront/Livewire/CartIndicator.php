<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Storefront\Livewire;

use App\Modules\Commerce\Storefront\Services\CartSessionService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Keeps the storefront header cart count synchronized across Livewire islands.
 */
final class CartIndicator extends Component
{
    /**
     * Trigger a component refresh after another cart component mutates state.
     */
    #[On('commerce-cart-updated')]
    public function refreshCart(): void {}

    public function render(CartSessionService $cart): View
    {
        return view('commerce::livewire.storefront.cart-indicator', [
            'cartCount' => $cart->count(),
        ]);
    }
}
