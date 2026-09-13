<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Availability\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/** Presents advisory quote search and operational availability-block history. */
final class AvailabilityController extends Controller
{
    /** Render the module-owned availability administration view. */
    public function __invoke(): View
    {
        return view('property-booking::admin.availability.index');
    }
}
