<?php

/**
 * Coordinates an HTTP request with TravelTours services and presentation.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Bookings\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\TravelTours\Bookings\Models\TourBooking;
use Illuminate\Contracts\View\View;

/** Presents retained booking records and payment state. */
final class BookingAdminController extends Controller
{
    /** Render the BookingAdminController response for an authorized request. */
    public function __invoke(): View
    {
        return view('travel-tours::admin.bookings.index', ['bookings' => TourBooking::query()->with(['tour', 'departure', 'customer'])->latest('placed_at')->paginate(20)]);
    }
}
