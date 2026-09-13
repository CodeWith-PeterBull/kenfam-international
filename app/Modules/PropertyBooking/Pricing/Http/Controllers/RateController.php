<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Pricing\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/** Presents rate-plan and bounded date-override administration. */
final class RateController extends Controller
{
    /** Render the module-owned pricing administration view. */
    public function __invoke(): View
    {
        return view('property-booking::admin.rates.index');
    }
}
