<?php

/**
 * Provides a documented component of the independent TravelTours module.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours;

use App\Modules\TravelTours\Bookings\Models\TourBooking;
use App\Modules\TravelTours\Bookings\Services\BookingDocumentService;
use App\Modules\TravelTours\Bookings\Services\BookingPaymentService;
use App\Modules\TravelTours\Bookings\Services\TourBookingService;
use App\Modules\TravelTours\Catalog\Models\Destination;
use App\Modules\TravelTours\Catalog\Models\Tour;
use App\Modules\TravelTours\Catalog\Services\TourSearchService;
use App\Modules\TravelTours\Contracts\CalculatesTourQuotes;
use App\Modules\TravelTours\Contracts\ChecksDepartureAvailability;
use App\Modules\TravelTours\Contracts\PlacesTourBookings;
use App\Modules\TravelTours\Contracts\ProcessesBookingPayments;
use App\Modules\TravelTours\Contracts\RendersBookingDocuments;
use App\Modules\TravelTours\Contracts\SearchesTours;
use App\Modules\TravelTours\Customers\Models\TravelCustomer;
use App\Modules\TravelTours\Events\BookingPaymentConfirmed;
use App\Modules\TravelTours\Events\TourBookingPlaced;
use App\Modules\TravelTours\Inquiries\Models\TourInquiry;
use App\Modules\TravelTours\Listeners\SendBookingPaymentConfirmedNotification;
use App\Modules\TravelTours\Listeners\SendTourBookingPlacedNotification;
use App\Modules\TravelTours\PointOfBooking\Models\BookingRegister;
use App\Modules\TravelTours\PointOfBooking\Models\BookingShift;
use App\Modules\TravelTours\Policies\BookingPolicy;
use App\Modules\TravelTours\Policies\CustomerPolicy;
use App\Modules\TravelTours\Policies\DeparturePolicy;
use App\Modules\TravelTours\Policies\InquiryPolicy;
use App\Modules\TravelTours\Policies\RegisterPolicy;
use App\Modules\TravelTours\Policies\ShiftPolicy;
use App\Modules\TravelTours\Policies\TourPolicy;
use App\Modules\TravelTours\Pricing\Services\TourQuoteCalculator;
use App\Modules\TravelTours\Scheduling\Models\TourDeparture;
use App\Modules\TravelTours\Scheduling\Services\DepartureAvailabilityService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/** Sole host entry point for the independently toggleable Travel & Tours module. */
final class TravelToursServiceProvider extends ServiceProvider
{
    /** Merge configuration before deciding whether to expose module resources. */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/Config/travel-tours.php', 'travel-tours');

        if (! config('travel-tours.enabled', false)) {
            return;
        }

        $this->app->bind(SearchesTours::class, TourSearchService::class);
        $this->app->bind(CalculatesTourQuotes::class, TourQuoteCalculator::class);
        $this->app->bind(ChecksDepartureAvailability::class, DepartureAvailabilityService::class);
        $this->app->bind(PlacesTourBookings::class, TourBookingService::class);
        $this->app->bind(RendersBookingDocuments::class, BookingDocumentService::class);
        $this->app->bind(ProcessesBookingPayments::class, BookingPaymentService::class);
    }

    /** Register only the resources of an enabled module. */
    public function boot(): void
    {
        if (! config('travel-tours.enabled', false)) {
            return;
        }

        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/Resources/views', 'travel-tours');
        $this->loadRoutesFrom(__DIR__.'/Routes/storefront.php');
        $this->loadRoutesFrom(__DIR__.'/Routes/admin.php');
        $this->loadRoutesFrom(__DIR__.'/Routes/pob.php');
        Event::listen(TourBookingPlaced::class, SendTourBookingPlacedNotification::class);
        Event::listen(BookingPaymentConfirmed::class, SendBookingPaymentConfirmedNotification::class);
        Gate::policy(Tour::class, TourPolicy::class);
        Gate::policy(Destination::class, TourPolicy::class);
        Gate::policy(TourDeparture::class, DeparturePolicy::class);
        Gate::policy(TourBooking::class, BookingPolicy::class);
        Gate::policy(TravelCustomer::class, CustomerPolicy::class);
        Gate::policy(TourInquiry::class, InquiryPolicy::class);
        Gate::policy(BookingRegister::class, RegisterPolicy::class);
        Gate::policy(BookingShift::class, ShiftPolicy::class);
    }
}
