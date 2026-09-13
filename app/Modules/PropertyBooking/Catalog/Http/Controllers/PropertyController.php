<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/** Presents the authorized property and category administration workspace. */
final class PropertyController extends Controller
{
    /** Render the module-owned property administration view. */
    public function __invoke(): View
    {
        return view('property-booking::admin.properties.index');
    }
}
