<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Storefront\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\PropertyBooking\Catalog\Models\Property;
use App\Modules\PropertyBooking\Storefront\Services\BookingSelectionSession;
use App\Modules\PropertyBooking\Storefront\Services\StorefrontNavigation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/** Presents selection review and Livewire checkout shells. */
final class StorefrontController extends Controller
{
    /** Create the controller with session and navigation readers. */
    public function __construct(
        private readonly BookingSelectionSession $selection,
        private readonly StorefrontNavigation $navigation,
    ) {}

    /** Render a current server-requoted selection or return to discovery. */
    public function selection(): View|RedirectResponse
    {
        $quote = $this->selection->currentOrNull();
        if ($quote === null) {
            return redirect()->route('property-booking.storefront.catalog.index')
                ->with('warning', 'Choose an available stay before continuing.');
        }

        return view('property-booking::storefront.selection.index', [
            'quote' => $quote,
            'property' => Property::query()->published()->findOrFail($quote->propertyId),
            'storefrontNavigationCategories' => $this->navigation->categories(),
        ]);
    }

    /** Render checkout only while the session selection still re-quotes. */
    public function checkout(): View|RedirectResponse
    {
        if ($this->selection->currentOrNull() === null) {
            return redirect()->route('property-booking.storefront.catalog.index')
                ->with('warning', 'Your stay selection expired. Please choose another available option.');
        }

        return view('property-booking::storefront.checkout.index', [
            'storefrontNavigationCategories' => $this->navigation->categories(),
        ]);
    }
}
