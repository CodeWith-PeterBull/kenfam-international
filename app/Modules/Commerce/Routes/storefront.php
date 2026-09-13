<?php

declare(strict_types=1);

use App\Modules\Commerce\Storefront\Http\Controllers\CatalogController;
use App\Modules\Commerce\Storefront\Http\Controllers\OrderAccessController;
use App\Modules\Commerce\Storefront\Http\Controllers\StorefrontController;
use Illuminate\Support\Facades\Route;

/**
 * Public Commerce storefront routes.
 *
 * Catalog, session cart, checkout, and temporary signed order routes remain
 * module-owned and available to guests through the standard web middleware.
 */
Route::middleware('web')
    ->prefix('shop')
    ->name('commerce.storefront.')
    ->group(function (): void {
        Route::get('/', [CatalogController::class, 'index'])->name('catalog.index');
        Route::get('/products/{product:slug}', [CatalogController::class, 'show'])->name('products.show');
        Route::get('/cart', [StorefrontController::class, 'cart'])->name('cart.index');
        Route::get('/checkout', [StorefrontController::class, 'checkout'])->name('checkout.index');

        Route::middleware('signed')->group(function (): void {
            Route::get('/orders/{order}/confirmation', [OrderAccessController::class, 'confirmation'])->name('orders.confirmation');
            Route::get('/orders/{order}/track', [OrderAccessController::class, 'track'])->name('orders.track');
            Route::get('/orders/{order}/document/{orientation}', [OrderAccessController::class, 'document'])->name('orders.document');
        });
    });
