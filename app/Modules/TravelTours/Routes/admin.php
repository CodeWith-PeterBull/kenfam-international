<?php

/**
 * Registers a named and middleware-scoped TravelTours route surface.
 */

declare(strict_types=1);

use App\Modules\TravelTours\Bookings\Http\Controllers\BookingAdminController;
use App\Modules\TravelTours\Catalog\Http\Controllers\CatalogAdminController;
use App\Modules\TravelTours\Catalog\Http\Controllers\DestinationAdminController;
use App\Modules\TravelTours\Catalog\Http\Controllers\TourCategoryAdminController;
use App\Modules\TravelTours\Inquiries\Http\Controllers\InquiryAdminController;
use App\Modules\TravelTours\Operations\Http\Controllers\TravelDashboardController;
use App\Modules\TravelTours\Scheduling\Http\Controllers\DepartureAdminController;
use App\Modules\TravelTours\Support\TravelToursPermission;
use Illuminate\Support\Facades\Route;

/** Authenticated travel administration boundary. */
Route::middleware(['web', 'auth', 'verified'])
    ->prefix('admin/travel')
    ->name('travel-tours.admin.')
    ->group(function (): void {
        Route::get('/', TravelDashboardController::class)->middleware('permission:'.TravelToursPermission::VIEW_DASHBOARD)->name('dashboard');
        Route::get('/catalog', CatalogAdminController::class)->middleware('permission:'.TravelToursPermission::VIEW_CATALOG)->name('catalog.index');
        Route::get('/catalog/categories', TourCategoryAdminController::class)->middleware('permission:'.TravelToursPermission::VIEW_CATALOG)->name('catalog.categories');
        Route::get('/catalog/destinations', DestinationAdminController::class)->middleware('permission:'.TravelToursPermission::VIEW_CATALOG)->name('catalog.destinations');
        Route::get('/departures', DepartureAdminController::class)->middleware('permission:'.TravelToursPermission::VIEW_DEPARTURES)->name('departures.index');
        Route::get('/bookings', BookingAdminController::class)->middleware('permission:'.TravelToursPermission::VIEW_BOOKINGS)->name('bookings.index');
        Route::get('/inquiries', InquiryAdminController::class)->middleware('permission:'.TravelToursPermission::VIEW_INQUIRIES)->name('inquiries.index');
    });
