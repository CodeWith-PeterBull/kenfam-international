<?php

/**
 * Registers a named and middleware-scoped TravelTours route surface.
 */

declare(strict_types=1);

use App\Modules\TravelTours\PointOfBooking\Http\Controllers\ReceiptController;
use App\Modules\TravelTours\PointOfBooking\Http\Controllers\TerminalController;
use App\Modules\TravelTours\Support\TravelToursPermission;
use Illuminate\Support\Facades\Route;

/** Full-width travel point-of-booking terminal boundary. */
Route::middleware(['web', 'auth', 'verified', 'permission:'.TravelToursPermission::ACCESS_POB])
    ->prefix('travel-booking-desk')
    ->name('travel-tours.pob.')
    ->group(function (): void {
        Route::get('/', TerminalController::class)->name('terminal');
        Route::get('/receipts/{payment:ulid}', ReceiptController::class)->name('receipt');
    });
