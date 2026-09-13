<?php

declare(strict_types=1);

namespace Tests\Feature\Commerce;

use App\Enums\ReportOrientation;
use App\Modules\Commerce\Catalog\Enums\ProductStatus;
use App\Modules\Commerce\Catalog\Models\Product;
use App\Modules\Commerce\Customers\Models\Customer;
use App\Modules\Commerce\Customers\Services\CustomerService;
use App\Modules\Commerce\Exceptions\InvalidCartException;
use App\Modules\Commerce\Orders\Data\CustomerSnapshotData;
use App\Modules\Commerce\Orders\Enums\OrderPaymentStatus;
use App\Modules\Commerce\Orders\Enums\PaymentMethod;
use App\Modules\Commerce\Orders\Enums\PaymentStatus;
use App\Modules\Commerce\Orders\Models\Order;
use App\Modules\Commerce\Orders\Notifications\OrderConfirmationNotification;
use App\Modules\Commerce\Storefront\Livewire\Checkout;
use App\Modules\Commerce\Storefront\Services\CartSessionService;
use App\Modules\Commerce\Storefront\Services\OrderAccessUrlService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Verifies session integrity, atomic placement, and signed post-order access.
 */
final class CommerceStorefrontOrderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_session_cart_stores_only_identifiers_and_recalculates_current_server_values(): void
    {
        $product = Product::factory()->published()->withStock(6)->create([
            'price_minor' => 12_500,
            'minimum_order_quantity' => 1,
            'maximum_order_quantity' => 4,
        ]);
        $cart = app(CartSessionService::class);

        $snapshot = $cart->add($product->id, 2);

        $this->assertSame([$product->id => 2], session((string) config('commerce.storefront.cart_session_key')));
        $this->assertSame(2, $snapshot->itemCount);
        $this->assertSame(25_000, $snapshot->calculation->totalMinor);

        $product->forceFill(['price_minor' => 14_000])->save();
        $recalculated = $cart->snapshot();

        $this->assertSame(28_000, $recalculated->calculation->totalMinor);
        $this->assertSame(14_000, $recalculated->calculation->lines[0]->unitPriceMinor);
    }

    public function test_cart_removes_hidden_products_and_rejects_stock_or_quantity_violations(): void
    {
        $product = Product::factory()->published()->withStock(2)->create([
            'minimum_order_quantity' => 1,
            'maximum_order_quantity' => 2,
        ]);
        $cart = app(CartSessionService::class);
        $cart->add($product->id, 2);

        $this->expectException(InvalidCartException::class);
        $cart->setQuantity($product->id, 3);
    }

    public function test_hidden_cart_lines_are_removed_with_a_customer_facing_issue(): void
    {
        $product = Product::factory()->published()->withStock(3)->create();
        $cart = app(CartSessionService::class);
        $cart->add($product->id);
        $product->forceFill(['status' => ProductStatus::Archived])->save();

        $snapshot = $cart->snapshot();

        $this->assertTrue($snapshot->isEmpty());
        $this->assertNotEmpty($snapshot->issues);
        $this->assertFalse(session()->has((string) config('commerce.storefront.cart_session_key')));
    }

    public function test_livewire_checkout_places_one_order_pending_payment_and_clears_the_cart(): void
    {
        Notification::fake();
        $product = Product::factory()->published()->withStock(5)->create(['price_minor' => 18_500]);
        app(CartSessionService::class)->add($product->id, 2);

        Livewire::test(Checkout::class)
            ->set('form.firstName', 'Amina')
            ->set('form.lastName', 'Otieno')
            ->set('form.email', 'amina@example.test')
            ->set('form.phone', '+254700000001')
            ->set('form.fulfillmentType', 'pickup')
            ->set('form.paymentMethod', PaymentMethod::MobileMoney->value)
            ->set('form.acceptedTerms', true)
            ->call('place')
            ->assertHasNoErrors()
            ->assertRedirect();

        $order = Order::query()->with(['items', 'payments'])->sole();
        $customer = Customer::query()->sole();
        $this->assertSame('Amina', $order->customer_first_name);
        $this->assertSame($customer->id, $order->customer_id);
        $this->assertSame(37_000, $order->total_minor);
        $this->assertSame(OrderPaymentStatus::Unpaid, $order->payment_status);
        $this->assertSame(1, $order->payments->count());
        $this->assertSame(PaymentStatus::Pending, $order->payments->sole()->status);
        $this->assertSame(PaymentMethod::MobileMoney, $order->payments->sole()->method);
        $this->assertSame(3, $product->stock()->firstOrFail()->on_hand);
        $this->assertFalse(session()->has((string) config('commerce.storefront.cart_session_key')));

        Notification::assertSentOnDemand(
            OrderConfirmationNotification::class,
            static fn (OrderConfirmationNotification $notification, array $channels, object $notifiable): bool => $notification->orderUlid === $order->ulid
                && $channels === ['mail']
                && data_get($notifiable, 'routes.mail') === 'amina@example.test',
        );

        $mail = (new OrderConfirmationNotification($order->ulid))->toMail(new AnonymousNotifiable);
        $this->assertStringContainsString((string) $order->order_number, (string) $mail->subject);
        $this->assertSame('Track your order', $mail->actionText);
        $this->assertStringContainsString('/shop/orders/'.$order->ulid.'/track', (string) $mail->actionUrl);
    }

    public function test_guest_checkout_customer_matching_uses_an_exact_identity_tuple(): void
    {
        $service = app(CustomerService::class);
        $first = $service->resolveForCheckout(new CustomerSnapshotData(
            firstName: 'Amina',
            lastName: 'Otieno',
            email: 'AMINA@example.test',
            phone: '+254700000001',
            city: 'Nairobi',
        ));
        $repeat = $service->resolveForCheckout(new CustomerSnapshotData(
            firstName: 'Amina',
            lastName: 'Otieno',
            email: 'amina@example.test',
            phone: '+254700000001',
            city: 'Kisumu',
        ));
        $sharedContact = $service->resolveForCheckout(new CustomerSnapshotData(
            firstName: 'Brian',
            lastName: 'Otieno',
            email: 'amina@example.test',
            phone: '+254700000001',
        ));

        $this->assertSame($first->id, $repeat->id);
        $this->assertSame('Kisumu', $repeat->city);
        $this->assertNotSame($first->id, $sharedContact->id);
        $this->assertDatabaseCount('customers', 2);
    }

    public function test_livewire_pagination_uses_the_bootstrap_theme(): void
    {
        $this->assertSame('bootstrap', config('livewire.pagination_theme'));
    }

    public function test_checkout_renders_the_loading_affordances(): void
    {
        $product = Product::factory()->published()->withStock(5)->create(['price_minor' => 18_500]);
        app(CartSessionService::class)->add($product->id, 1);

        Livewire::test(Checkout::class)
            ->assertSeeHtml('class="commerce-checkout-status"')
            ->assertSeeHtml('wire:target.except="place"')
            ->assertSeeHtml('commerce-spinner')
            ->assertSee('Updating your order')
            ->assertSee('Placing order');
    }

    public function test_invalid_checkout_keeps_cart_and_creates_no_partial_records(): void
    {
        Notification::fake();
        $product = Product::factory()->published()->withStock(3)->create();
        app(CartSessionService::class)->add($product->id);

        Livewire::test(Checkout::class)
            ->set('form.firstName', '')
            ->set('form.email', 'invalid')
            ->set('form.acceptedTerms', false)
            ->call('place')
            ->assertHasErrors(['form.firstName', 'form.lastName', 'form.email', 'form.phone', 'form.acceptedTerms']);

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame([$product->id => 1], session((string) config('commerce.storefront.cart_session_key')));
        Notification::assertNothingSent();
    }

    public function test_confirmation_tracking_and_documents_require_their_exact_temporary_signatures(): void
    {
        Notification::fake();
        $product = Product::factory()->published()->withStock(4)->create();
        app(CartSessionService::class)->add($product->id);

        Livewire::test(Checkout::class)
            ->set('form.firstName', 'Njeri')
            ->set('form.lastName', 'Wanjiku')
            ->set('form.email', 'njeri@example.test')
            ->set('form.phone', '+254700000002')
            ->set('form.acceptedTerms', true)
            ->call('place');

        $order = Order::query()->sole();
        $order->forceFill(['internal_note' => 'INTERNAL-ONLY-CONTEXT'])->save();
        $urls = app(OrderAccessUrlService::class);

        $this->get(route('commerce.storefront.orders.confirmation', $order))->assertForbidden();
        $confirmation = $this->get($urls->confirmation($order));
        $confirmation
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
            ->assertSee($order->order_number)
            ->assertDontSee('INTERNAL-ONLY-CONTEXT');
        $this->assertStringContainsString('private', (string) $confirmation->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', (string) $confirmation->headers->get('Cache-Control'));
        $this->get($urls->tracking($order))
            ->assertOk()
            ->assertSee('Current status')
            ->assertDontSee('INTERNAL-ONLY-CONTEXT');

        $tampered = str_replace($order->ulid, (string) Str::ulid(), $urls->tracking($order));
        $this->get($tampered)->assertNotFound();

        $expiringConfirmation = $urls->confirmation($order);
        $this->travel(3)->hours();
        $this->get($expiringConfirmation)->assertForbidden();
        $this->travelBack();

        foreach (ReportOrientation::cases() as $orientation) {
            $response = $this->get($urls->document($order, $orientation));
            $response->assertOk()
                ->assertHeader('Content-Type', 'application/pdf');
            $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
            $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
            $this->assertStringStartsWith('%PDF', $response->getContent());
        }
    }
}
