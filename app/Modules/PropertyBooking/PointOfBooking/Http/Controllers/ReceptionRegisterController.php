<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\PointOfBooking\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/** Presents property-scoped reception register administration. */
final class ReceptionRegisterController extends Controller
{
    /** Render the module-owned register administration view. */
    public function __invoke(): View
    {
        return view('property-booking::pob.admin.registers.index');
    }
}
