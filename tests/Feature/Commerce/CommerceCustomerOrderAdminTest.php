<?php

declare(strict_types=1);

namespace Tests\Feature\Commerce;

use App\Enums\ReportOrientation;
use App\Enums\UserType;
use App\Models\User;
use App\Modules\Commerce\Catalog\Models\Product;
use App\Modules\Commerce\Customers\Livewire\Admin\CustomerManager;
use App\Modules\Commerce\Customers\Models\Customer;
use App\Modules\Commerce\Orders\Data\CartItemData;
use App\Modules\Commerce\Orders\Data\CustomerSnapshotData;
use App\Modules\Commerce\Orders\Data\OrderPlacementData;
use App\Modules\Commerce\Orders\Enums\FulfillmentType;
use App\Modules\Commerce\Orders\Enums\OrderPaymentStatus;
use App\Modules\Commerce\Orders\Enums\OrderStatus;
use App\Modules\Commerce\Orders\Enums\PaymentMethod;
use App\Modules\Commerce\Orders\Enums\PaymentStatus;
use App\Modules\Commerce\Orders\Livewire\Admin\OrderManager;
use App\Modules\Commerce\Orders\Models\Order;
use App\Modules\Commerce\Orders\Services\OrderService;
use App\Modules\Commerce\Orders\Services\PaymentService;
use App\Modules\Commerce\Support\CommercePermission;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Verifies permission-gated customer and order administration workflows.
 */
final class CommerceCustomerOrderAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $administrator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->administrator = User::factory()->create(['user_type' => UserType::SystemAdministrator]);
        $this->administrator->assignRole(UserType::SystemAdministrator->value);
    }

    public function test_customer_and_order_routes_enforce_their_specific_permissions(): void
    {
        $this->get(route('commerce.admin.customers.index'))->assertRedirect(route('login'));
        $this->get(route('commerce.admin.orders.index'))->assertRedirect(route('login'));

        $user = User::factory()->create();
        $this->actingAs($user)->get(route('commerce.admin.customers.index'))->assertForbidden();
        $this->actingAs($user)->get(route('commerce.admin.orders.index'))->assertForbidden();

        $user->givePermissionTo(CommercePermission::MANAGE_CUSTOMERS, CommercePermission::VIEW_ORDERS);
        $this->actingAs($user)->get(route('commerce.admin.customers.index'))
            ->assertOk()
            ->assertSeeLivewire('commerce.admin.customer-manager');
        $this->actingAs($user)->get(route('commerce.admin.orders.index'))
            ->assertOk()
            ->assertSeeLivewire('commerce.admin.order-manager');
    }

    public function test_administrator_can_create_update_archive_and_restore_a_customer(): void
    {
        $account = User::factory()->create(['email' => 'linked@example.test']);

        Livewire::actingAs($this->administrator)
            ->test(CustomerManager::class)
            ->call('openCreate')
            ->set('form.userId', (string) $account->id)
            ->set('form.firstName', 'Akinyi')
            ->set('form.lastName', 'Odhiambo')
            ->set('form.email', 'AKINYI@example.test')
            ->set('form.phone', '+254700000003')
            ->set('form.addressLine1', '14 Riverside Drive')
            ->set('form.city', 'Nairobi')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Customer created.');

        $customer = Customer::query()->sole();
        $this->assertSame($account->id, $customer->user_id);
        $this->assertSame('akinyi@example.test', $customer->email);

        Livewire::actingAs($this->administrator)
            ->test(CustomerManager::class)
            ->call('openEdit', $customer->id)
            ->set('form.company', 'Akinyi Consulting')
            ->call('save')
            ->assertHasNoErrors()
            ->call('archive', $customer->id)
            ->assertHasNoErrors();

        $this->assertSoftDeleted('customers', ['id' => $customer->id]);

        Livewire::actingAs($this->administrator)
            ->test(CustomerManager::class)
            ->set('state', 'archived')
            ->call('restore', $customer->id)
            ->assertHasNoErrors();

        $this->assertNotSoftDeleted('customers', ['id' => $customer->id]);
        $this->assertSame('Akinyi Consulting', $customer->refresh()->company);
        $this->assertDatabaseHas('system_activities', ['activity_type' => 'commerce.customer.restored']);
    }

    public function test_order_manager_advances_fulfillment_and_reuses_pending_payment_preference(): void
    {
        $product = Product::factory()->published()->withStock(6)->create(['price_minor' => 20_000]);
        $order = app(OrderService::class)->placeWebOrder($this->placement($product, 2));
        app(PaymentService::class)->recordPendingPreference($order, PaymentMethod::BankTransfer);

        Livewire::actingAs($this->administrator)
            ->test(OrderManager::class)
            ->assertSee($order->order_number)
            ->call('advanceOrder', $order->id, OrderStatus::Confirmed->value)
            ->assertHasNoErrors()
            ->call('openPayment', $order->id)
            ->set('paymentForm.reference', 'BANK-ORDER-001')
            ->call('recordPayment')
            ->assertHasNoErrors();

        $order->refresh();
        $payment = $order->payments()->sole();
        $this->assertSame(OrderStatus::Confirmed, $order->status);
        $this->assertSame(OrderPaymentStatus::Paid, $order->payment_status);
        $this->assertSame(PaymentStatus::Completed, $payment->status);
        $this->assertSame('BANK-ORDER-001', $payment->reference);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_order_manager_cancels_an_unpaid_web_order_and_restores_stock_once(): void
    {
        $product = Product::factory()->published()->withStock(5)->create();
        $order = app(OrderService::class)->placeWebOrder($this->placement($product, 3));
        $this->assertSame(2, $product->stock()->firstOrFail()->on_hand);

        Livewire::actingAs($this->administrator)
            ->test(OrderManager::class)
            ->call('openCancellation', $order->id)
            ->set('cancellationReason', 'Customer requested cancellation')
            ->call('cancel')
            ->assertHasNoErrors();

        $this->assertSame(OrderStatus::Cancelled, $order->refresh()->status);
        $this->assertSame(5, $product->stock()->firstOrFail()->on_hand);
        $this->assertSame(2, $order->stockMovements()->count());
    }

    public function test_authorized_order_documents_support_both_orientations_and_are_audited(): void
    {
        $product = Product::factory()->published()->withStock(3)->create();
        $order = app(OrderService::class)->placeWebOrder($this->placement($product));

        foreach (ReportOrientation::cases() as $orientation) {
            $response = $this->actingAs($this->administrator)->get(route('commerce.admin.orders.document', [
                'order' => $order,
                'orientation' => $orientation,
            ]));
            $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
            $this->assertStringContainsString('attachment;', (string) $response->headers->get('Content-Disposition'));
            $this->assertStringStartsWith('%PDF', $response->getContent());
        }

        $this->assertDatabaseCount('system_activities', 3);
        $this->assertDatabaseHas('system_activities', ['activity_type' => 'commerce.order.document_downloaded']);
    }

    private function placement(Product $product, int $quantity = 1): OrderPlacementData
    {
        return new OrderPlacementData(
            items: [new CartItemData($product->id, $quantity)],
            customer: new CustomerSnapshotData(
                firstName: 'Wanjiru',
                lastName: 'Kamau',
                email: 'wanjiru@example.test',
                phone: '+254700000004',
            ),
            fulfillmentType: FulfillmentType::Pickup,
            preferredPaymentMethod: PaymentMethod::BankTransfer,
        );
    }
}
