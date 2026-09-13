<?php

declare(strict_types=1);

namespace App\Modules\Commerce;

use App\Modules\Commerce\Catalog\Livewire\Admin\BarcodeLabelSheet;
use App\Modules\Commerce\Catalog\Livewire\Admin\ProductCatalog;
use App\Modules\Commerce\Catalog\Livewire\Admin\ProductCategoryManager;
use App\Modules\Commerce\Catalog\Models\Product;
use App\Modules\Commerce\Catalog\Models\ProductCategory;
use App\Modules\Commerce\Catalog\Policies\ProductCategoryPolicy;
use App\Modules\Commerce\Catalog\Policies\ProductPolicy;
use App\Modules\Commerce\Customers\Livewire\Admin\CustomerManager;
use App\Modules\Commerce\Customers\Models\Customer;
use App\Modules\Commerce\Customers\Policies\CustomerPolicy;
use App\Modules\Commerce\DemoData\Livewire\Admin\DemoDataManager;
use App\Modules\Commerce\Inventory\Events\StockBecameLow;
use App\Modules\Commerce\Inventory\Events\StockDepleted;
use App\Modules\Commerce\Inventory\Livewire\Admin\InventoryManager;
use App\Modules\Commerce\Inventory\Models\Stock;
use App\Modules\Commerce\Inventory\Models\StockMovement;
use App\Modules\Commerce\Inventory\Policies\StockMovementPolicy;
use App\Modules\Commerce\Inventory\Policies\StockPolicy;
use App\Modules\Commerce\Notifications\Listeners\SendCustomerOrderCancelledNotification;
use App\Modules\Commerce\Notifications\Listeners\SendCustomerOrderReadyNotification;
use App\Modules\Commerce\Notifications\Listeners\SendCustomerPaymentConfirmedNotification;
use App\Modules\Commerce\Notifications\Listeners\SendLowStockNotification;
use App\Modules\Commerce\Notifications\Listeners\SendNewWebOrderReceivedNotification;
use App\Modules\Commerce\Notifications\Listeners\SendOutOfStockNotification;
use App\Modules\Commerce\Notifications\Listeners\SendTillVarianceNotification;
use App\Modules\Commerce\Orders\Events\OrderCancelled;
use App\Modules\Commerce\Orders\Events\OrderPaymentConfirmed;
use App\Modules\Commerce\Orders\Events\OrderReady;
use App\Modules\Commerce\Orders\Events\WebOrderPlaced;
use App\Modules\Commerce\Orders\Livewire\Admin\OrderManager;
use App\Modules\Commerce\Orders\Models\Order;
use App\Modules\Commerce\Orders\Models\Payment;
use App\Modules\Commerce\Orders\Policies\OrderPolicy;
use App\Modules\Commerce\Orders\Policies\PaymentPolicy;
use App\Modules\Commerce\PointOfSale\Events\TillVarianceDetected;
use App\Modules\Commerce\PointOfSale\Livewire\Admin\RegisterManager;
use App\Modules\Commerce\PointOfSale\Livewire\Admin\TillManager;
use App\Modules\Commerce\PointOfSale\Livewire\CashierSalesHistory;
use App\Modules\Commerce\PointOfSale\Livewire\Terminal;
use App\Modules\Commerce\PointOfSale\Models\Register;
use App\Modules\Commerce\PointOfSale\Models\TillSession;
use App\Modules\Commerce\PointOfSale\Policies\RegisterPolicy;
use App\Modules\Commerce\PointOfSale\Policies\TillSessionPolicy;
use App\Modules\Commerce\Storefront\Livewire\AddToCart;
use App\Modules\Commerce\Storefront\Livewire\CartIndicator;
use App\Modules\Commerce\Storefront\Livewire\CartManager;
use App\Modules\Commerce\Storefront\Livewire\CatalogBrowser;
use App\Modules\Commerce\Storefront\Livewire\Checkout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

/**
 * Registers the self-contained Commerce module with the Aureon host.
 *
 * This provider remains the only host-level entry point for Commerce
 * configuration, migrations, routes, policies, views, and Livewire aliases.
 */
final class CommerceServiceProvider extends ServiceProvider
{
    /**
     * Merge module configuration without requiring adopters to publish it.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/Config/commerce.php', 'commerce');
    }

    /**
     * Load module-owned persistence, routing, and presentation resources.
     */
    public function boot(): void
    {
        if (! config('commerce.enabled', true)) {
            return;
        }

        $this->registerPolicies();
        $this->registerLivewireComponents();
        $this->registerEventListeners();

        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/Resources/views', 'commerce');

        $this->loadRoutesFrom(__DIR__.'/Routes/admin.php');
        $this->loadRoutesFrom(__DIR__.'/Routes/pos.php');
        $this->loadRoutesFrom(__DIR__.'/Routes/storefront.php');
    }

    /**
     * Register stable aliases so module components remain independent of discovery paths.
     */
    private function registerLivewireComponents(): void
    {
        Livewire::component('commerce.admin.product-catalog', ProductCatalog::class);
        Livewire::component('commerce.admin.barcode-label-sheet', BarcodeLabelSheet::class);
        Livewire::component('commerce.admin.product-category-manager', ProductCategoryManager::class);
        Livewire::component('commerce.admin.inventory-manager', InventoryManager::class);
        Livewire::component('commerce.admin.customer-manager', CustomerManager::class);
        Livewire::component('commerce.admin.order-manager', OrderManager::class);
        Livewire::component('commerce.admin.demo-data-manager', DemoDataManager::class);
        Livewire::component('commerce.pos.admin.register-manager', RegisterManager::class);
        Livewire::component('commerce.pos.admin.till-manager', TillManager::class);
        Livewire::component('commerce.pos.cashier-sales-history', CashierSalesHistory::class);
        Livewire::component('commerce.pos.terminal', Terminal::class);
        Livewire::component('commerce.storefront.catalog-browser', CatalogBrowser::class);
        Livewire::component('commerce.storefront.add-to-cart', AddToCart::class);
        Livewire::component('commerce.storefront.cart-indicator', CartIndicator::class);
        Livewire::component('commerce.storefront.cart-manager', CartManager::class);
        Livewire::component('commerce.storefront.checkout', Checkout::class);
    }

    /**
     * Register explicit module policies without relying on root namespace discovery.
     */
    private function registerPolicies(): void
    {
        Gate::policy(ProductCategory::class, ProductCategoryPolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(Stock::class, StockPolicy::class);
        Gate::policy(StockMovement::class, StockMovementPolicy::class);
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(Payment::class, PaymentPolicy::class);
        Gate::policy(Register::class, RegisterPolicy::class);
        Gate::policy(TillSession::class, TillSessionPolicy::class);
    }

    /**
     * Register the module's explicit one-event-to-one-notification listeners.
     */
    private function registerEventListeners(): void
    {
        Event::listen(WebOrderPlaced::class, SendNewWebOrderReceivedNotification::class);
        Event::listen(OrderPaymentConfirmed::class, SendCustomerPaymentConfirmedNotification::class);
        Event::listen(OrderReady::class, SendCustomerOrderReadyNotification::class);
        Event::listen(OrderCancelled::class, SendCustomerOrderCancelledNotification::class);
        Event::listen(StockBecameLow::class, SendLowStockNotification::class);
        Event::listen(StockDepleted::class, SendOutOfStockNotification::class);
        Event::listen(TillVarianceDetected::class, SendTillVarianceNotification::class);
    }
}
