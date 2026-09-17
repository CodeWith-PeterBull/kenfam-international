<?php

/**
 * Coordinates an HTTP request with TravelTours services and presentation.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Storefront\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\TravelTours\Catalog\Models\Destination;
use App\Modules\TravelTours\Catalog\Models\TourCategory;
use App\Modules\TravelTours\Contracts\SearchesTours;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/** Presents the public tour catalog with bounded discovery filters. */
final class StorefrontController extends Controller
{
    /** Render the StorefrontController response for an authorized request. */
    public function __invoke(Request $request, SearchesTours $search): View
    {
        $filters = $request->validate([
            'keyword' => ['nullable', 'string', 'max:120'], 'destination' => ['nullable', 'string', 'max:180'],
            'category' => ['nullable', 'string', 'max:180'], 'type' => ['nullable', 'string', 'max:24'],
            'departure_date' => ['nullable', 'date'], 'maximum_duration_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        return view('travel-tours::storefront.catalog.index', [
            'tours' => $search->search($filters, (int) config('travel-tours.storefront.page_size', 12)),
            'destinations' => Destination::query()->published()->orderBy('sort_order')->orderBy('name')->limit(100)->get(),
            'categories' => TourCategory::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
            'filters' => $filters,
        ]);
    }
}
