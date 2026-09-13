<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Bookings\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/** Presents the authorized booking operations register. */
final class BookingController extends Controller
{
    /** Render the property-scoped booking administration workspace. */
    public function __invoke(): View
    {
        return view('property-booking::admin.bookings.index');
    }
}
