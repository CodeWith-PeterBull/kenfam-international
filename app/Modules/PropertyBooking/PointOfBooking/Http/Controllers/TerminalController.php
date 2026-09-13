<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\PointOfBooking\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/** Presents the dedicated full-width reception booking workspace. */
final class TerminalController extends Controller
{
    /** Render the module-owned terminal shell. */
    public function __invoke(): View
    {
        return view('property-booking::pob.terminal.index');
    }
}
