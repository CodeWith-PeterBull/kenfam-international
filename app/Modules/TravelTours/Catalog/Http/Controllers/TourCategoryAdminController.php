<?php

/** Presents TravelTours category administration. */

declare(strict_types=1);

namespace App\Modules\TravelTours\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/** Render the module-owned category manager shell. */
final class TourCategoryAdminController extends Controller
{
    /** Return the authorized category administration page. */
    public function __invoke(): View
    {
        return view('travel-tours::admin.catalog.categories');
    }
}
