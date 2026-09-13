<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Storefront\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Commerce\Storefront\Services\CartSessionService;
use App\Modules\Commerce\Storefront\Services\StorefrontNavigation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * Presents public cart and checkout shells while Livewire owns page state.
 */
final class StorefrontController extends Controller
{
    public function __construct(private readonly StorefrontNavigation $navigation) {}

    /**
     * Render the session-backed cart workspace.
     */
    public function cart(): View
    {
        return view('commerce::storefront.cart.index', [
            'storefrontNavigationCategories' => $this->navigation->categories(),
        ]);
    }

    /**
     * Render checkout only while a valid session cart still contains lines.
     */
    public function checkout(CartSessionService $cart): View|RedirectResponse
    {
        if ($cart->snapshot()->isEmpty()) {
            return redirect()->route('commerce.storefront.cart.index')
                ->with('warning', 'Add at least one product before checkout.');
        }

        return view('commerce::storefront.checkout.index', [
            'storefrontNavigationCategories' => $this->navigation->categories(),
        ]);
    }
}
