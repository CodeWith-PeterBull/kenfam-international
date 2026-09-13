<?php

declare(strict_types=1);

use App\Modules\Commerce\Catalog\Http\Controllers\Admin\CatalogBarcodeController;
use App\Modules\Commerce\DemoData\Http\Controllers\DemoDataController;
use App\Modules\Commerce\Http\Controllers\Admin\CatalogController;
use App\Modules\Commerce\Http\Controllers\Admin\CommerceDashboardController;
use App\Modules\Commerce\Http\Controllers\Admin\CustomerController;
use App\Modules\Commerce\Http\Controllers\Admin\InventoryController;
use App\Modules\Commerce\Http\Controllers\Admin\OrderController;
use App\Modules\Commerce\Support\CommercePermission;
use Illuminate\Support\Facades\Route;

/**
 * Commerce administration routes.
 */
Route::middleware(['web', 'auth', 'verified'])
    ->prefix('admin/commerce')
    ->name('commerce.admin.')
    ->group(function (): void {
        Route::get('/', CommerceDashboardController::class)
            ->middleware('permission:'.CommercePermission::VIEW_DASHBOARD)
            ->name('dashboard');

        Route::get('/catalog', [CatalogController::class, 'index'])
            ->middleware('permission:'.CommercePermission::VIEW_PRODUCTS)
            ->name('catalog.index');

        Route::get('/catalog/barcodes', [CatalogBarcodeController::class, 'index'])
            ->middleware('permission:'.CommercePermission::VIEW_PRODUCTS)
            ->name('catalog.barcodes');

        Route::get('/inventory', [InventoryController::class, 'index'])
            ->middleware('permission:'.CommercePermission::VIEW_INVENTORY)
            ->name('inventory.index');

        Route::get('/customers', [CustomerController::class, 'index'])
            ->middleware('permission:'.CommercePermission::MANAGE_CUSTOMERS)
            ->name('customers.index');

        Route::get('/orders', [OrderController::class, 'index'])
            ->middleware('permission:'.CommercePermission::VIEW_ORDERS)
            ->name('orders.index');

        Route::get('/orders/{order}/document/{orientation}', [OrderController::class, 'document'])
            ->middleware('permission:'.CommercePermission::VIEW_ORDERS)
            ->name('orders.document');

        Route::get('/demo-data', DemoDataController::class)
            ->middleware('permission:'.CommercePermission::MANAGE_DEMO_DATA)
            ->name('demo-data.index');
    });
