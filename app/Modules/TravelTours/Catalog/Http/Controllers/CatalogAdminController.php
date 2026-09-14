<?php

/**
 * Coordinates an HTTP request with TravelTours services and presentation.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\TravelTours\Catalog\Models\Destination;
use App\Modules\TravelTours\Catalog\Models\Tour;
use App\Modules\TravelTours\Catalog\Models\TourCategory;
use Illuminate\Contracts\View\View;

/** Presents the travel catalog administration overview. */
final class CatalogAdminController extends Controller
{
    /** Render the CatalogAdminController response for an authorized request. */
    public function __invoke(): View
    {
        return view('travel-tours::admin.catalog.index', ['tours' => Tour::query()->with(['categories', 'destinations'])->latest()->paginate(20), 'categoryCount' => TourCategory::query()->count(), 'destinationCount' => Destination::query()->count()]);
    }
}
