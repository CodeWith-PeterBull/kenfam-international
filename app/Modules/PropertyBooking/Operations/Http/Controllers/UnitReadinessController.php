<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Operations\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/** Presents the authorized housekeeping readiness board. */
final class UnitReadinessController extends Controller
{
    /** Render the property-scoped accommodation readiness workspace. */
    public function __invoke(): View
    {
        return view('property-booking::admin.readiness.index');
    }
}
