<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/** Presents sellable unit types and concrete accommodation inventory. */
final class UnitController extends Controller
{
    /** Render the module-owned unit administration view. */
    public function __invoke(): View
    {
        return view('property-booking::admin.units.index');
    }
}
