<?php

/**
 * Coordinates an HTTP request with TravelTours services and presentation.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\PointOfBooking\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/** Opens the full-width booking-desk workspace; the terminal itself is Livewire. */
final class TerminalController extends Controller
{
    /** Render the terminal shell for an authorised operator. */
    public function __invoke(): View
    {
        return view('travel-tours::pob.terminal.index');
    }
}
