<?php

/**
 * Coordinates an HTTP request with TravelTours services and presentation.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Scheduling\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\TravelTours\Scheduling\Models\TourDeparture;
use Illuminate\Contracts\View\View;

/** Presents scheduled departures and current capacity state. */
final class DepartureAdminController extends Controller
{
    /** Render the DepartureAdminController response for an authorized request. */
    public function __invoke(): View
    {
        return view('travel-tours::admin.departures.index', ['departures' => TourDeparture::query()->with(['tour', 'ratePlan'])->orderBy('starts_at')->paginate(20)]);
    }
}
