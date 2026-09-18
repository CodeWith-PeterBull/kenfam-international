<?php

/**
 * Coordinates an HTTP request with TravelTours services and presentation.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\PointOfBooking\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/** Presents the register and shift workspace; the manager itself is Livewire. */
final class ShiftAdminController extends Controller
{
    /** Render the ShiftAdminController response for an authorized request. */
    public function __invoke(): View
    {
        return view('travel-tours::admin.shifts.index');
    }
}
