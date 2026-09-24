<?php

/**
 * Coordinates an HTTP request with TravelTours services and presentation.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Operations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\TravelTours\Bookings\Enums\BookingStatus;
use App\Modules\TravelTours\Bookings\Models\TourBooking;
use App\Modules\TravelTours\Catalog\Models\Tour;
use App\Modules\TravelTours\Inquiries\Models\TourInquiry;
use App\Modules\TravelTours\Scheduling\Enums\DepartureStatus;
use App\Modules\TravelTours\Scheduling\Models\TourDeparture;
use App\Modules\TravelTours\Support\TravelToursPermission;
use Illuminate\Contracts\View\View;

/** Composes a read-only travel operations snapshot without business writes. */
final class TravelDashboardController extends Controller
{
    /** Render the TravelDashboardController response for an authorized request. */
    public function __invoke(): View
    {
        return view('travel-tours::admin.dashboard.index', [
            'stats' => [
                ['label' => 'Published tours', 'value' => Tour::query()->published()->count(), 'icon' => 'map-route', 'route' => 'travel-tours.admin.catalog.index', 'permission' => TravelToursPermission::VIEW_CATALOG],
                ['label' => 'Upcoming departures', 'value' => TourDeparture::query()->whereIn('status', [DepartureStatus::Open->value, DepartureStatus::Guaranteed->value])->where('starts_at', '>', now())->count(), 'icon' => 'calendar-event', 'route' => 'travel-tours.admin.departures.index', 'permission' => TravelToursPermission::VIEW_DEPARTURES],
                ['label' => 'Active bookings', 'value' => TourBooking::query()->whereIn('status', [BookingStatus::Pending->value, BookingStatus::Confirmed->value])->count(), 'icon' => 'ticket', 'route' => 'travel-tours.admin.bookings.index', 'permission' => TravelToursPermission::VIEW_BOOKINGS],
                ['label' => 'Open inquiries', 'value' => TourInquiry::query()->actionable()->count(), 'icon' => 'messages', 'route' => 'travel-tours.admin.inquiries.index', 'permission' => TravelToursPermission::VIEW_INQUIRIES],
            ],
            'departures' => TourDeparture::query()->with('tour')->where('starts_at', '>', now())->orderBy('starts_at')->limit(6)->get(),
            'bookings' => TourBooking::query()->with('customer')->latest('placed_at')->limit(6)->get(),
            'inquiries' => TourInquiry::query()->actionable()->orderByRaw('follow_up_at IS NULL, follow_up_at')->limit(6)->get(),
        ]);
    }
}
