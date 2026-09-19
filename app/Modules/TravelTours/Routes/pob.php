<?php

/**
 * Registers a named and middleware-scoped TravelTours route surface.
 */

declare(strict_types=1);

use App\Modules\TravelTours\PointOfBooking\Http\Controllers\ReceiptController;
use App\Modules\TravelTours\PointOfBooking\Http\Controllers\RegisterAdminController;
use App\Modules\TravelTours\PointOfBooking\Http\Controllers\ShiftAdminController;
use App\Modules\TravelTours\PointOfBooking\Http\Controllers\TerminalController;
use App\Modules\TravelTours\Support\TravelToursPermission;
use Illuminate\Support\Facades\Route;

/** Full-width booking-desk terminal and private receipt routes. */
Route::middleware(['web', 'auth', 'verified'])
    ->prefix('travel-booking-desk')
    ->name('travel-tours.pob.')
    ->group(function (): void {
        Route::get('/', TerminalController::class)
            ->middleware('permission:'.TravelToursPermission::ACCESS_POB)
            ->name('terminal');
        Route::get('/receipts/{booking:ulid}', [ReceiptController::class, 'show'])->name('receipts.show');
        Route::get('/receipts/{booking:ulid}/pdf', [ReceiptController::class, 'pdf'])->name('receipts.pdf');
    });

/** Register and shift management stay inside the dashboard authorisation boundary. */
Route::middleware(['web', 'auth', 'verified', 'permission:'.TravelToursPermission::MANAGE_SHIFTS])
    ->prefix('admin/travel/pob')
    ->name('travel-tours.pob.admin.')
    ->group(function (): void {
        Route::get('/registers', RegisterAdminController::class)->name('registers.index');
        Route::get('/shifts', ShiftAdminController::class)->name('shifts.index');
    });
