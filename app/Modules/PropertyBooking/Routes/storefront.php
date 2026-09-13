<?php

declare(strict_types=1);

use App\Modules\PropertyBooking\Storefront\Http\Controllers\BookingAccessController;
use App\Modules\PropertyBooking\Storefront\Http\Controllers\CatalogController;
use App\Modules\PropertyBooking\Storefront\Http\Controllers\StorefrontController;
use Illuminate\Support\Facades\Route;

/** Public discovery, selection, checkout, and private signed booking routes. */
Route::middleware(['web', 'throttle:120,1'])
    ->prefix('stays')
    ->name('property-booking.storefront.')
    ->scopeBindings()
    ->group(function (): void {
        Route::get('/', [CatalogController::class, 'index'])->name('catalog.index');
        Route::get('/selection', [StorefrontController::class, 'selection'])->name('selection.index');
        Route::get('/checkout', [StorefrontController::class, 'checkout'])->name('checkout.index');

        Route::middleware('signed')->group(function (): void {
            Route::get('/bookings/{booking}/confirmation', [BookingAccessController::class, 'confirmation'])->name('bookings.confirmation');
            Route::get('/bookings/{booking}/track', [BookingAccessController::class, 'track'])->name('bookings.track');
            Route::get('/bookings/{booking}/document/{orientation}', [BookingAccessController::class, 'document'])->name('bookings.document');
        });

        Route::get('/{property:slug}/{unitType:slug}', [CatalogController::class, 'showUnit'])->name('units.show');
        Route::get('/{property:slug}', [CatalogController::class, 'show'])->name('properties.show');
    });
