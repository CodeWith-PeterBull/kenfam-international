<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Guests\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/** Presents the authorized reusable guest directory. */
final class GuestController extends Controller
{
    /** Render the protected guest administration workspace. */
    public function __invoke(): View
    {
        return view('property-booking::admin.guests.index');
    }
}
