<?php

/**
 * Coordinates an HTTP request with TravelTours services and presentation.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\PointOfBooking\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\TravelTours\PointOfBooking\Models\BookingShift;
use Illuminate\Contracts\View\View;

/** Opens the full-width booking-desk workspace for the authenticated operator. */
final class TerminalController extends Controller
{
    /** Render the TerminalController response for an authorized request. */
    public function __invoke(): View
    {
        return view('travel-tours::pob.terminal.index', ['shift' => BookingShift::query()->with('register')->where('operator_id', auth()->id())->open()->latest('opened_at')->first()]);
    }
}
