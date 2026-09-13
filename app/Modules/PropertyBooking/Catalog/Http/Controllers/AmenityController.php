<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/** Presents the authorized reusable amenity administration workspace. */
final class AmenityController extends Controller
{
    /** Render the module-owned amenity administration view. */
    public function __invoke(): View
    {
        return view('property-booking::admin.amenities.index');
    }
}
