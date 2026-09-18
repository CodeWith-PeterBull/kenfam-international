<?php

/**
 * Coordinates an HTTP request with TravelTours services and presentation.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Storefront\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\TravelTours\Catalog\Models\Tour;
use App\Modules\TravelTours\Scheduling\Services\DepartureAvailabilityService;
use Illuminate\Contracts\View\View;

/** Presents a publication-gated tour detail aggregate. */
final class TourController extends Controller
{
    /** Render the TourController response for an authorized request. */
    public function __invoke(Tour $tour, DepartureAvailabilityService $availability): View
    {
        abort_unless(Tour::query()->published()->whereKey($tour->getKey())->exists(), 404);
        $tour->load(['categories', 'destinations', 'itineraryDays.activities', 'contentItems', 'faqs', 'extras', 'media',
            'ratePlans' => fn ($query) => $query->publiclyAvailable()->with('participantRates'),
            'departures' => fn ($query) => $query->bookable()->with('ratePlan.participantRates')->limit(12),
        ]);

        $departureAvailability = $tour->departures->mapWithKeys(fn ($departure): array => [$departure->id => $availability->check($departure)]);

        return view('travel-tours::storefront.catalog.show', compact('tour', 'departureAvailability'));
    }
}
