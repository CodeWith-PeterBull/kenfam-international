<?php

/**
 * Coordinates an HTTP request with TravelTours services and presentation.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Scheduling\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/** Presents scheduled departures and current capacity state. */
final class DepartureAdminController extends Controller
{
    /** Render the shared scheduling workspace after route middleware authorization. */
    public function __invoke(): View
    {
        return view('travel-tours::admin.departures.index');
    }
}
