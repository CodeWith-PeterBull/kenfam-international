<?php

declare(strict_types=1);

use App\Modules\Commerce\PointOfSale\Http\Controllers\ReceiptController;
use App\Modules\Commerce\PointOfSale\Http\Controllers\RegisterController;
use App\Modules\Commerce\PointOfSale\Http\Controllers\TerminalController;
use App\Modules\Commerce\PointOfSale\Http\Controllers\TillController;
use App\Modules\Commerce\Support\CommercePermission;
use Illuminate\Support\Facades\Route;

/**
 * Commerce POS routes.
 *
 * Terminal, register, till, and receipt surfaces remain isolated from general
 * Commerce administration.
 */
Route::middleware(['web', 'auth', 'verified'])
    ->prefix('pos')
    ->name('commerce.pos.')
    ->group(function (): void {
        Route::get('/', [TerminalController::class, 'index'])
            ->middleware('permission:'.CommercePermission::ACCESS_POS)
            ->name('terminal');
        Route::get('/receipts/{order:ulid}', [ReceiptController::class, 'show'])->name('receipts.show');
        Route::get('/receipts/{order:ulid}/pdf', [ReceiptController::class, 'pdf'])->name('receipts.pdf');
    });

Route::middleware([
    'web',
    'auth',
    'verified',
    'permission:'.CommercePermission::MANAGE_TILLS,
])
    ->prefix('admin/commerce/pos')
    ->name('commerce.pos.admin.')
    ->group(function (): void {
        Route::get('/registers', [RegisterController::class, 'index'])->name('registers.index');
        Route::get('/tills', [TillController::class, 'index'])->name('tills.index');
    });
