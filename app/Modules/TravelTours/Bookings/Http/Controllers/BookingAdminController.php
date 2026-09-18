<?php

/**
 * Coordinates an HTTP request with TravelTours services and presentation.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Bookings\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/** Presents the booking workspace; listing and actions live in the Livewire manager. */
final class BookingAdminController extends Controller
{
    /** Render the BookingAdminController response for an authorized request. */
    public function __invoke(): View
    {
        return view('travel-tours::admin.bookings.index');
    }
}
