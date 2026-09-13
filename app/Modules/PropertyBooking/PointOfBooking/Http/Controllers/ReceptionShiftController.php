<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\PointOfBooking\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/** Presents property-scoped reception shift administration. */
final class ReceptionShiftController extends Controller
{
    /** Render the module-owned shift administration view. */
    public function __invoke(): View
    {
        return view('property-booking::pob.admin.shifts.index');
    }
}
