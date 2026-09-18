<?php

/**
 * Registers a named and middleware-scoped TravelTours route surface.
 */

declare(strict_types=1);

use App\Modules\TravelTours\Bookings\Http\Controllers\BookingAdminController;
use App\Modules\TravelTours\Catalog\Http\Controllers\CatalogAdminController;
use App\Modules\TravelTours\Catalog\Http\Controllers\DestinationAdminController;
use App\Modules\TravelTours\Catalog\Http\Controllers\TourCategoryAdminController;
use App\Modules\TravelTours\Catalog\Http\Controllers\TourEditorController;
use App\Modules\TravelTours\Inquiries\Http\Controllers\InquiryAdminController;
use App\Modules\TravelTours\Operations\Http\Controllers\TravelDashboardController;
use App\Modules\TravelTours\PointOfBooking\Http\Controllers\ShiftAdminController;
use App\Modules\TravelTours\Pricing\Http\Controllers\PricingAdminController;
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
        Route::get('/catalog/tours/create', [TourEditorController::class, 'create'])->middleware('permission:'.TravelToursPermission::MANAGE_CATALOG)->name('catalog.tours.create');
        Route::get('/catalog/tours/{tour}/edit', [TourEditorController::class, 'edit'])->middleware('permission:'.TravelToursPermission::MANAGE_CATALOG)->name('catalog.tours.edit');
        Route::get('/catalog/tours/{tour}/preview', [TourEditorController::class, 'preview'])->middleware('permission:'.TravelToursPermission::VIEW_CATALOG)->name('catalog.tours.preview');
        Route::get('/catalog/categories', TourCategoryAdminController::class)->middleware('permission:'.TravelToursPermission::VIEW_CATALOG)->name('catalog.categories');
        Route::get('/catalog/destinations', DestinationAdminController::class)->middleware('permission:'.TravelToursPermission::VIEW_CATALOG)->name('catalog.destinations');
        Route::get('/pricing', [PricingAdminController::class, 'index'])->middleware('permission:'.TravelToursPermission::VIEW_PRICING)->name('pricing.index');
        Route::get('/pricing/promotions', [PricingAdminController::class, 'promotions'])->middleware('permission:'.TravelToursPermission::VIEW_PRICING)->name('pricing.promotions');
        Route::get('/pricing/tours/{tour}', [PricingAdminController::class, 'tour'])->middleware('permission:'.TravelToursPermission::VIEW_PRICING)->name('pricing.tour');
        Route::get('/departures', DepartureAdminController::class)->middleware('permission:'.TravelToursPermission::VIEW_DEPARTURES)->name('departures.index');
        Route::get('/bookings', BookingAdminController::class)->middleware('permission:'.TravelToursPermission::VIEW_BOOKINGS)->name('bookings.index');
        Route::get('/shifts', ShiftAdminController::class)->middleware('permission:'.TravelToursPermission::MANAGE_SHIFTS)->name('shifts.index');
        Route::get('/inquiries', InquiryAdminController::class)->middleware('permission:'.TravelToursPermission::VIEW_INQUIRIES)->name('inquiries.index');
    });
