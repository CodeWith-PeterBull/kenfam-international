<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking;

use App\Modules\PropertyBooking\Availability\Livewire\Admin\AvailabilityManager;
use App\Modules\PropertyBooking\Availability\Models\AvailabilityBlock;
use App\Modules\PropertyBooking\Availability\Policies\AvailabilityBlockPolicy;
use App\Modules\PropertyBooking\Availability\Services\UnitAllocationService;
use App\Modules\PropertyBooking\Bookings\Events\BookingCancelled;
use App\Modules\PropertyBooking\Bookings\Events\BookingCheckedIn;
use App\Modules\PropertyBooking\Bookings\Events\BookingCheckedOut;
use App\Modules\PropertyBooking\Bookings\Events\BookingConfirmed;
use App\Modules\PropertyBooking\Bookings\Events\BookingMarkedNoShow;
use App\Modules\PropertyBooking\Bookings\Events\BookingModified;
use App\Modules\PropertyBooking\Bookings\Events\BookingPaymentConfirmed;
use App\Modules\PropertyBooking\Bookings\Events\WebBookingPlaced;
use App\Modules\PropertyBooking\Bookings\Listeners\SendBookingCancellationAlertNotification;
use App\Modules\PropertyBooking\Bookings\Listeners\SendBookingCancelledNotification;
use App\Modules\PropertyBooking\Bookings\Listeners\SendBookingCheckedInNotification;
use App\Modules\PropertyBooking\Bookings\Listeners\SendBookingCheckedOutNotification;
use App\Modules\PropertyBooking\Bookings\Listeners\SendBookingConfirmationNotification;
use App\Modules\PropertyBooking\Bookings\Listeners\SendBookingConfirmedNotification;
use App\Modules\PropertyBooking\Bookings\Listeners\SendBookingModifiedNotification;
use App\Modules\PropertyBooking\Bookings\Listeners\SendBookingNoShowNotification;
use App\Modules\PropertyBooking\Bookings\Listeners\SendBookingPaymentConfirmedNotification;
use App\Modules\PropertyBooking\Bookings\Listeners\SendNewWebBookingReceivedNotification;
use App\Modules\PropertyBooking\Bookings\Listeners\SendUnitTurnoverRequiredNotification;
use App\Modules\PropertyBooking\Bookings\Livewire\Admin\BookingManager;
use App\Modules\PropertyBooking\Bookings\Models\Booking;
use App\Modules\PropertyBooking\Bookings\Models\BookingCharge;
use App\Modules\PropertyBooking\Bookings\Models\BookingPayment;
use App\Modules\PropertyBooking\Bookings\Models\BookingStay;
use App\Modules\PropertyBooking\Bookings\Models\UnitAssignment;
use App\Modules\PropertyBooking\Bookings\Policies\BookingChargePolicy;
use App\Modules\PropertyBooking\Bookings\Policies\BookingPaymentPolicy;
use App\Modules\PropertyBooking\Bookings\Policies\BookingPolicy;
use App\Modules\PropertyBooking\Bookings\Policies\BookingStayPolicy;
use App\Modules\PropertyBooking\Bookings\Policies\UnitAssignmentPolicy;
use App\Modules\PropertyBooking\Catalog\Livewire\Admin\AccommodationUnitManager;
use App\Modules\PropertyBooking\Catalog\Livewire\Admin\AmenityManager;
use App\Modules\PropertyBooking\Catalog\Livewire\Admin\PropertyCategoryManager;
use App\Modules\PropertyBooking\Catalog\Livewire\Admin\PropertyManager;
use App\Modules\PropertyBooking\Catalog\Livewire\Admin\UnitTypeManager;
use App\Modules\PropertyBooking\Catalog\Models\AccommodationUnit;
use App\Modules\PropertyBooking\Catalog\Models\Amenity;
use App\Modules\PropertyBooking\Catalog\Models\Property;
use App\Modules\PropertyBooking\Catalog\Models\PropertyCategory;
use App\Modules\PropertyBooking\Catalog\Models\UnitType;
use App\Modules\PropertyBooking\Catalog\Policies\AccommodationUnitPolicy;
use App\Modules\PropertyBooking\Catalog\Policies\AmenityPolicy;
use App\Modules\PropertyBooking\Catalog\Policies\PropertyCategoryPolicy;
use App\Modules\PropertyBooking\Catalog\Policies\PropertyPolicy;
use App\Modules\PropertyBooking\Catalog\Policies\UnitTypePolicy;
use App\Modules\PropertyBooking\Contracts\AllocatesUnits;
use App\Modules\PropertyBooking\Contracts\CalculatesBookingRates;
use App\Modules\PropertyBooking\Guests\Livewire\Admin\GuestManager;
use App\Modules\PropertyBooking\Guests\Models\Guest;
use App\Modules\PropertyBooking\Guests\Policies\GuestPolicy;
use App\Modules\PropertyBooking\Operations\Livewire\Admin\UnitReadinessManager;
use App\Modules\PropertyBooking\PointOfBooking\Events\ReceptionShiftVarianceDetected;
use App\Modules\PropertyBooking\PointOfBooking\Listeners\SendReceptionShiftVarianceNotification;
use App\Modules\PropertyBooking\PointOfBooking\Livewire\Admin\ReceptionRegisterManager;
use App\Modules\PropertyBooking\PointOfBooking\Livewire\Admin\ReceptionShiftManager;
use App\Modules\PropertyBooking\PointOfBooking\Livewire\Terminal;
use App\Modules\PropertyBooking\PointOfBooking\Models\ReceptionRegister;
use App\Modules\PropertyBooking\PointOfBooking\Models\ReceptionShift;
use App\Modules\PropertyBooking\PointOfBooking\Policies\ReceptionRegisterPolicy;
use App\Modules\PropertyBooking\PointOfBooking\Policies\ReceptionShiftPolicy;
use App\Modules\PropertyBooking\Pricing\Livewire\Admin\RateOverrideManager;
use App\Modules\PropertyBooking\Pricing\Livewire\Admin\RatePlanManager;
use App\Modules\PropertyBooking\Pricing\Models\RateOverride;
use App\Modules\PropertyBooking\Pricing\Models\RatePlan;
use App\Modules\PropertyBooking\Pricing\Policies\RateOverridePolicy;
use App\Modules\PropertyBooking\Pricing\Policies\RatePlanPolicy;
use App\Modules\PropertyBooking\Pricing\Services\BookingRateCalculator;
use App\Modules\PropertyBooking\Storefront\Livewire\AvailabilityBrowser;
use App\Modules\PropertyBooking\Storefront\Livewire\Checkout;
use App\Modules\PropertyBooking\Storefront\Livewire\PropertyAvailability;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

/** Sole host entry point for the independently toggleable Property Booking module. */
final class PropertyBookingServiceProvider extends ServiceProvider
{
    /** Merge configuration and expose only enabled module contracts. */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/Config/property-booking.php', 'property-booking');

        if (! config('property-booking.enabled', true)) {
            return;
        }

        $this->app->bind(CalculatesBookingRates::class, BookingRateCalculator::class);
        $this->app->bind(AllocatesUnits::class, UnitAllocationService::class);
    }

    /** Register persistence, policies, route boundaries, and namespaced views. */
    public function boot(): void
    {
        if (! config('property-booking.enabled', true)) {
            return;
        }

        $this->registerPolicies();
        $this->registerLivewireComponents();
        $this->registerEventListeners();
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/Resources/views', 'property-booking');
        $this->loadRoutesFrom(__DIR__.'/Routes/admin.php');
        $this->loadRoutesFrom(__DIR__.'/Routes/pob.php');
        $this->loadRoutesFrom(__DIR__.'/Routes/storefront.php');
    }

    /** Register stable aliases independent of root component discovery. */
    private function registerLivewireComponents(): void
    {
        Livewire::component('property-booking.admin.property-manager', PropertyManager::class);
        Livewire::component('property-booking.admin.property-category-manager', PropertyCategoryManager::class);
        Livewire::component('property-booking.admin.amenity-manager', AmenityManager::class);
        Livewire::component('property-booking.admin.unit-type-manager', UnitTypeManager::class);
        Livewire::component('property-booking.admin.accommodation-unit-manager', AccommodationUnitManager::class);
        Livewire::component('property-booking.admin.rate-plan-manager', RatePlanManager::class);
        Livewire::component('property-booking.admin.rate-override-manager', RateOverrideManager::class);
        Livewire::component('property-booking.admin.availability-manager', AvailabilityManager::class);
        Livewire::component('property-booking.admin.booking-manager', BookingManager::class);
        Livewire::component('property-booking.admin.guest-manager', GuestManager::class);
        Livewire::component('property-booking.admin.unit-readiness-manager', UnitReadinessManager::class);
        Livewire::component('property-booking.storefront.availability-browser', AvailabilityBrowser::class);
        Livewire::component('property-booking.storefront.property-availability', PropertyAvailability::class);
        Livewire::component('property-booking.storefront.checkout', Checkout::class);
        Livewire::component('property-booking.pob.admin.reception-register-manager', ReceptionRegisterManager::class);
        Livewire::component('property-booking.pob.admin.reception-shift-manager', ReceptionShiftManager::class);
        Livewire::component('property-booking.pob.terminal', Terminal::class);
    }

    /** Register focused post-commit notification listeners for each operation. */
    private function registerEventListeners(): void
    {
        Event::listen(WebBookingPlaced::class, SendBookingConfirmationNotification::class);
        Event::listen(WebBookingPlaced::class, SendNewWebBookingReceivedNotification::class);
        Event::listen(BookingConfirmed::class, SendBookingConfirmedNotification::class);
        Event::listen(BookingModified::class, SendBookingModifiedNotification::class);
        Event::listen(BookingCancelled::class, SendBookingCancelledNotification::class);
        Event::listen(BookingCancelled::class, SendBookingCancellationAlertNotification::class);
        Event::listen(BookingMarkedNoShow::class, SendBookingNoShowNotification::class);
        Event::listen(BookingCheckedIn::class, SendBookingCheckedInNotification::class);
        Event::listen(BookingCheckedOut::class, SendBookingCheckedOutNotification::class);
        Event::listen(BookingCheckedOut::class, SendUnitTurnoverRequiredNotification::class);
        Event::listen(BookingPaymentConfirmed::class, SendBookingPaymentConfirmedNotification::class);
        Event::listen(ReceptionShiftVarianceDetected::class, SendReceptionShiftVarianceNotification::class);
    }

    /** Register explicit policies without relying on root namespace discovery. */
    private function registerPolicies(): void
    {
        Gate::policy(PropertyCategory::class, PropertyCategoryPolicy::class);
        Gate::policy(Property::class, PropertyPolicy::class);
        Gate::policy(Amenity::class, AmenityPolicy::class);
        Gate::policy(UnitType::class, UnitTypePolicy::class);
        Gate::policy(AccommodationUnit::class, AccommodationUnitPolicy::class);
        Gate::policy(RatePlan::class, RatePlanPolicy::class);
        Gate::policy(RateOverride::class, RateOverridePolicy::class);
        Gate::policy(AvailabilityBlock::class, AvailabilityBlockPolicy::class);
        Gate::policy(Guest::class, GuestPolicy::class);
        Gate::policy(Booking::class, BookingPolicy::class);
        Gate::policy(BookingStay::class, BookingStayPolicy::class);
        Gate::policy(UnitAssignment::class, UnitAssignmentPolicy::class);
        Gate::policy(BookingCharge::class, BookingChargePolicy::class);
        Gate::policy(BookingPayment::class, BookingPaymentPolicy::class);
        Gate::policy(ReceptionRegister::class, ReceptionRegisterPolicy::class);
        Gate::policy(ReceptionShift::class, ReceptionShiftPolicy::class);
    }
}
