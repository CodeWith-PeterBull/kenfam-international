<?php

declare(strict_types=1);

use App\Modules\PropertyBooking\Availability\Http\Controllers\AvailabilityController;
use App\Modules\PropertyBooking\Bookings\Http\Controllers\BookingController;
use App\Modules\PropertyBooking\Catalog\Http\Controllers\AmenityController;
use App\Modules\PropertyBooking\Catalog\Http\Controllers\PropertyController;
use App\Modules\PropertyBooking\Catalog\Http\Controllers\UnitController;
use App\Modules\PropertyBooking\Guests\Http\Controllers\GuestController;
use App\Modules\PropertyBooking\Operations\Http\Controllers\OperationsDashboardController;
use App\Modules\PropertyBooking\Operations\Http\Controllers\UnitReadinessController;
use App\Modules\PropertyBooking\Pricing\Http\Controllers\RateController;
use App\Modules\PropertyBooking\Support\PropertyBookingPermission;
use Illuminate\Support\Facades\Route;

/** Property Booking administration routes. */
Route::middleware(['web', 'auth', 'verified'])
    ->prefix('admin/accommodation')
    ->name('property-booking.admin.')
    ->group(function (): void {
        Route::get('/', OperationsDashboardController::class)
            ->middleware('permission:'.PropertyBookingPermission::VIEW_DASHBOARD)
            ->name('dashboard');

        Route::get('/properties', PropertyController::class)
            ->middleware('permission:'.PropertyBookingPermission::VIEW_PROPERTIES)
            ->name('properties.index');

        Route::get('/amenities', AmenityController::class)
            ->middleware('permission:'.PropertyBookingPermission::VIEW_PROPERTIES)
            ->name('amenities.index');

        Route::get('/units', UnitController::class)
            ->middleware('permission:'.PropertyBookingPermission::VIEW_PROPERTIES)
            ->name('units.index');

        Route::get('/rates', RateController::class)
            ->middleware('permission:'.PropertyBookingPermission::VIEW_RATES)
            ->name('rates.index');

        Route::get('/availability', AvailabilityController::class)
            ->middleware('permission:'.PropertyBookingPermission::VIEW_AVAILABILITY)
            ->name('availability.index');

        Route::get('/bookings', BookingController::class)
            ->middleware('permission:'.PropertyBookingPermission::VIEW_BOOKINGS.'|'.PropertyBookingPermission::MANAGE_BOOKINGS)
            ->name('bookings.index');

        Route::get('/guests', GuestController::class)
            ->middleware('permission:'.PropertyBookingPermission::VIEW_BOOKINGS.'|'.PropertyBookingPermission::MANAGE_GUESTS)
            ->name('guests.index');

        Route::get('/readiness', UnitReadinessController::class)
            ->middleware('permission:'.PropertyBookingPermission::MANAGE_READINESS)
            ->name('readiness.index');
    });
