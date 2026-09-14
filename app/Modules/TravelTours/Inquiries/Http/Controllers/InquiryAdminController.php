<?php

/**
 * Coordinates an HTTP request with TravelTours services and presentation.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Inquiries\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\TravelTours\Inquiries\Models\TourInquiry;
use Illuminate\Contracts\View\View;

/** Presents the inquiry follow-up queue. */
final class InquiryAdminController extends Controller
{
    /** Render the InquiryAdminController response for an authorized request. */
    public function __invoke(): View
    {
        return view('travel-tours::admin.inquiries.index', ['inquiries' => TourInquiry::query()->with(['tour', 'assignee'])->latest()->paginate(20)]);
    }
}
