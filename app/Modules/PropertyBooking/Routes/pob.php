<?php

declare(strict_types=1);

use App\Modules\PropertyBooking\PointOfBooking\Http\Controllers\ReceiptController;
use App\Modules\PropertyBooking\PointOfBooking\Http\Controllers\ReceptionRegisterController;
use App\Modules\PropertyBooking\PointOfBooking\Http\Controllers\ReceptionShiftController;
use App\Modules\PropertyBooking\PointOfBooking\Http\Controllers\TerminalController;
use App\Modules\PropertyBooking\Support\PropertyBookingPermission;
use Illuminate\Support\Facades\Route;

/** Full-width Point of Booking terminal and private receipt routes. */
Route::middleware(['web', 'auth', 'verified'])
    ->prefix('pob')
    ->name('property-booking.pob.')
    ->group(function (): void {
        Route::get('/', TerminalController::class)
            ->middleware('permission:'.PropertyBookingPermission::ACCESS_POB)
            ->name('terminal');
        Route::get('/receipts/{booking:ulid}', [ReceiptController::class, 'show'])->name('receipts.show');
        Route::get('/receipts/{booking:ulid}/pdf', [ReceiptController::class, 'pdf'])->name('receipts.pdf');
    });

/** Register and shift management remain inside the dashboard authorization boundary. */
Route::middleware([
    'web',
    'auth',
    'verified',
    'permission:'.PropertyBookingPermission::MANAGE_SHIFTS,
])
    ->prefix('admin/accommodation/pob')
    ->name('property-booking.pob.admin.')
    ->group(function (): void {
        Route::get('/registers', ReceptionRegisterController::class)->name('registers.index');
        Route::get('/shifts', ReceptionShiftController::class)->name('shifts.index');
    });
