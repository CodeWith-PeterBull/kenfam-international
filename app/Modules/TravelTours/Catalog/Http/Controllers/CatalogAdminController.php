<?php

/**
 * Coordinates an HTTP request with TravelTours services and presentation.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\TravelTours\Catalog\Services\CatalogQueryService;
use Illuminate\Contracts\View\View;

/** Presents the travel catalog administration overview. */
final class CatalogAdminController extends Controller
{
    /** Render the CatalogAdminController response for an authorized request. */
    public function __invoke(CatalogQueryService $catalog): View
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 401);

        return view('travel-tours::admin.catalog.index', [
            'tours' => $catalog->tours($actor)->with(['categories', 'destinations'])->latest()->paginate(20),
            'categoryCount' => $catalog->categories($actor)->count(),
            'destinationCount' => $catalog->destinations($actor)->count(),
        ]);
    }
}
