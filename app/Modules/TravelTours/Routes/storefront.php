<?php

/**
 * Registers a named and middleware-scoped TravelTours route surface.
 */

declare(strict_types=1);

use App\Modules\TravelTours\Storefront\Http\Controllers\BookingAccessController;
use App\Modules\TravelTours\Storefront\Http\Controllers\InquiryController;
use App\Modules\TravelTours\Storefront\Http\Controllers\StorefrontController;
use App\Modules\TravelTours\Storefront\Http\Controllers\TourController;
use Illuminate\Support\Facades\Route;
use Spatie\Honeypot\ProtectAgainstSpam;

/** Public tour discovery and inquiry routes. */
Route::middleware(['web', 'throttle:120,1'])
    ->prefix('tours')
    ->name('travel-tours.storefront.')
    ->group(function (): void {
        Route::get('/', StorefrontController::class)->name('catalog.index');
        Route::post('/inquiries', [InquiryController::class, 'store'])->middleware(ProtectAgainstSpam::class)->name('inquiries.store');
        Route::middleware('signed')->group(function (): void {
            Route::get('/bookings/{booking}/confirmation', [BookingAccessController::class, 'confirmation'])->name('bookings.confirmation');
            Route::get('/bookings/{booking}/track', [BookingAccessController::class, 'track'])->name('bookings.track');
            Route::get('/bookings/{booking}/document/{orientation}', [BookingAccessController::class, 'document'])->where('orientation', 'portrait|landscape')->name('bookings.document');
        });
        Route::get('/{tour:slug}', TourController::class)->name('tours.show');
    });
