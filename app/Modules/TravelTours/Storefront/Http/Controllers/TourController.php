<?php

/**
 * Coordinates an HTTP request with TravelTours services and presentation.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Storefront\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\TravelTours\Catalog\Models\Tour;
use Illuminate\Contracts\View\View;

/** Presents a publication-gated tour detail aggregate. */
final class TourController extends Controller
{
    /** Render the TourController response for an authorized request. */
    public function __invoke(Tour $tour): View
    {
        abort_unless(Tour::query()->published()->whereKey($tour->getKey())->exists(), 404);
        $tour->load(['categories', 'destinations', 'itineraryDays.activities', 'contentItems', 'faqs', 'extras', 'ratePlans.participantRates', 'departures' => fn ($query) => $query->bookable()->limit(12)]);

        return view('travel-tours::storefront.catalog.show', compact('tour'));
    }
}
