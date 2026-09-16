<?php

/** Presents TravelTours destination administration. */

declare(strict_types=1);

namespace App\Modules\TravelTours\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/** Render the module-owned destination manager shell. */
final class DestinationAdminController extends Controller
{
    /** Return the authorized destination administration page. */
    public function __invoke(): View
    {
        return view('travel-tours::admin.catalog.destinations');
    }
}
