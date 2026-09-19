<?php

/**
 * Coordinates an HTTP request with TravelTours services and presentation.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Storefront\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * Presents the public tour catalog. The filters, results, and pagination are
 * the TourSearch Livewire component, which reads its initial state from the
 * same query string this route has always accepted.
 */
final class StorefrontController extends Controller
{
    /** Render the catalog page around the live search. */
    public function __invoke(): View
    {
        return view('travel-tours::storefront.catalog.index');
    }
}
