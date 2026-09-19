<?php

/**
 * Coordinates an HTTP request with TravelTours services and presentation.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Inquiries\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/** Presents the inquiry follow-up queue; the manager itself is Livewire. */
final class InquiryAdminController extends Controller
{
    /** Render the inquiry workspace for an authorized operator. */
    public function __invoke(): View
    {
        return view('travel-tours::admin.inquiries.index');
    }
}
