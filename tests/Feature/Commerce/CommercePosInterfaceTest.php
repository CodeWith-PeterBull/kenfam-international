<?php

declare(strict_types=1);

namespace Tests\Feature\Commerce;

use App\Enums\UserType;
use App\Models\User;
use App\Modules\Commerce\Catalog\Models\Product;
use App\Modules\Commerce\Orders\Enums\OrderStatus;
use App\Modules\Commerce\Orders\Enums\PaymentMethod;
use App\Modules\Commerce\Orders\Models\Order;
use App\Modules\Commerce\PointOfSale\Enums\TillSessionStatus;
use App\Modules\Commerce\PointOfSale\Livewire\Admin\RegisterManager;
use App\Modules\Commerce\PointOfSale\Livewire\Admin\TillManager;
use App\Modules\Commerce\PointOfSale\Livewire\Terminal;
use App\Modules\Commerce\PointOfSale\Models\Register;
use App\Modules\Commerce\PointOfSale\Models\TillSession;
use App\Modules\Commerce\PointOfSale\Services\TillService;
use App\Modules\Commerce\Support\CommercePermission;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Verifies the operational POS interfaces against cashier ownership rules.
 */
final class CommercePosInterfaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_pos_routes_enforce_terminal_and_till_permissions(): void
    {
        $plainUser = User::factory()->create();
        $cashier = $this->userWith(CommercePermission::ACCESS_POS);
        $manager = $this->userWith(CommercePermission::MANAGE_TILLS);

        $this->get(route('commerce.pos.terminal'))->assertRedirect(route('login'));
        $this->actingAs($plainUser)->get(route('commerce.pos.terminal'))->assertForbidden();
        $this->actingAs($cashier)->get(route('commerce.pos.terminal'))->assertOk()->assertSee('Open a till before taking payments');
        $this->actingAs($cashier)->get(route('commerce.pos.admin.registers.index'))->assertForbidden();
        $this->actingAs($cashier)->get(route('commerce.pos.admin.tills.index'))->assertForbidden();
        $this->actingAs($manager)->get(route('commerce.pos.terminal'))->assertForbidden();
        $this->actingAs($manager)->get(route('commerce.pos.admin.registers.index'))->assertOk();
        $this->actingAs($manager)->get(route('commerce.pos.admin.tills.index'))->assertOk();
    }

    public function test_active_system_administrator_can_access_all_pos_surfaces_when_seeded_permissions_are_stale(): void
    {
        $role = Role::findByName(UserType::SystemAdministrator->value, 'web');
        $role->revokePermissionTo(CommercePermission::ACCESS_POS);
        $role->revokePermissionTo(CommercePermission::MANAGE_TILLS);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $administrator = User::factory()->create([
            'user_type' => UserType::SystemAdministrator,
        ]);
        $administrator->assignRole($role);

        $this->assertFalse($role->fresh()->hasPermissionTo(CommercePermission::ACCESS_POS));
        $this->assertFalse($role->fresh()->hasPermissionTo(CommercePermission::MANAGE_TILLS));
        $this->actingAs($administrator)->get(route('commerce.pos.terminal'))->assertOk();
        $this->actingAs($administrator)->get(route('commerce.pos.admin.registers.index'))->assertOk();
        $this->actingAs($administrator)->get(route('commerce.pos.admin.tills.index'))->assertOk();
    }

    public function test_authenticated_browser_session_reaches_pos_administration_after_login(): void
    {
        $administrator = User::factory()->create([
            'user_type' => UserType::SystemAdministrator,
        ]);
        $administrator->assignRole(UserType::SystemAdministrator->value);

        $this->post('/login', [
            'email' => $administrator->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($administrator);
        $registers = $this->get(route('commerce.pos.admin.registers.index'))
            ->assertOk()
            ->assertSee('aria-label="Register statistics"', false);
        $tills = $this->get(route('commerce.pos.admin.tills.index'))
            ->assertOk()
            ->assertSee('aria-label="Till statistics"', false);

        $this->assertSame(3, substr_count($registers->getContent(), 'card aureon-stat mb-4'));
        $this->assertSame(4, substr_count($tills->getContent(), 'card aureon-stat mb-4'));
    }

    public function test_register_and_till_livewire_managers_complete_the_operational_setup(): void
    {
        $manager = $this->userWith(CommercePermission::MANAGE_TILLS);
        $cashier = $this->userWith(CommercePermission::ACCESS_POS);

        Livewire::actingAs($manager)
            ->test(RegisterManager::class)
            ->call('openCreate')
            ->set('form.name', 'Front Counter')
            ->set('form.code', 'front-01')
            ->set('form.locationLabel', 'Ground floor')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('dialog', '');

        $register = Register::query()->sole();
        $this->assertSame('FRONT-01', $register->code);

        Livewire::actingAs($manager)
            ->test(TillManager::class)
            ->call('openTillDialog')
            ->set('openForm.registerId', (string) $register->id)
            ->set('openForm.cashierId', (string) $cashier->id)
            ->set('openForm.openingFloat', '2500.00')
            ->call('openTill')
            ->assertHasNoErrors()
            ->assertSet('dialog', '');

        $session = TillSession::query()->sole();
        $this->assertSame($cashier->id, $session->opened_by);
        $this->assertSame(250_000, $session->opening_float_minor);

        Livewire::actingAs($manager)
            ->test(RegisterManager::class)
            ->call('toggleActive', $register->id)
            ->assertHasErrors('management');

        Livewire::actingAs($manager)
            ->test(TillManager::class)
            ->call('openCloseDialog', $session->id)
            ->set('closeForm.countedCash', '2500.00')
            ->call('closeTill')
            ->assertHasNoErrors();

        $this->assertSame(TillSessionStatus::Closed, $session->fresh()->status);
    }

    public function test_cashier_can_find_hold_resume_and_discard_a_sale_owned_by_the_open_till(): void
    {
        [$cashier, $session] = $this->cashierWithTill();
        $product = $this->product('SCAN-100', '616161616161', 10_000, 5);

        $component = Livewire::actingAs($cashier)
            ->test(Terminal::class)
            ->set('search', '616161616161')
            ->call('lookup')
            ->assertSet("cart.{$product->id}", 1)
            ->call('hold')
            ->assertHasNoErrors()
            ->assertSet('cart', []);

        $held = Order::query()->sole();
        $this->assertSame(OrderStatus::Held, $held->status);
        $this->assertSame($session->id, $held->till_session_id);

        $component
            ->call('resume', $held->id)
            ->assertSet('resumingOrderId', $held->id)
            ->assertSet("cart.{$product->id}", 1)
            ->call('discard', $held->id)
            ->assertSet('cart', [])
            ->assertSet('resumingOrderId', null);

        $this->assertSame(OrderStatus::Cancelled, $held->fresh()->status);
        $this->assertSame(5, $product->stock()->firstOrFail()->on_hand);
        $this->assertDatabaseHas('system_activities', ['activity_type' => 'commerce.pos.hold_discarded']);
    }

    public function test_terminal_completes_exact_split_tender_and_redirects_to_owned_receipt(): void
    {
        [$cashier] = $this->cashierWithTill();
        $product = $this->product('SPLIT-100', '717171717171', 10_000, 4);

        $component = Livewire::actingAs($cashier)
            ->test(Terminal::class)
            ->call('addProduct', $product->id)
            ->set('tenders', [
                ['method' => PaymentMethod::Cash->value, 'amount' => '40.00', 'tendered' => '50.00', 'reference' => ''],
                ['method' => PaymentMethod::MobileMoney->value, 'amount' => '60.00', 'tendered' => '', 'reference' => 'MPESA-POS-001'],
            ])
            ->call('checkout')
            ->assertHasNoErrors();

        $order = Order::query()->sole();
        $this->assertSame(OrderStatus::Completed, $order->status);
        $this->assertSame($cashier->id, $order->cashier_id);
        $this->assertSame(2, $order->payments()->count());
        $this->assertSame(3, $product->stock()->firstOrFail()->on_hand);
        $this->assertSame(1_000, $order->payments()->where('method', PaymentMethod::Cash->value)->sole()->change_minor);
        $component->assertRedirect(route('commerce.pos.receipts.show', [
            'order' => $order,
            'print' => 'checkout',
        ]));
    }

    public function test_terminal_rejects_a_minimum_quantity_above_available_stock(): void
    {
        [$cashier] = $this->cashierWithTill();
        $product = $this->product('MINIMUM-100', null, 10_000, 2);
        $product->forceFill(['minimum_order_quantity' => 3])->save();

        Livewire::actingAs($cashier)
            ->test(Terminal::class)
            ->call('addProduct', $product->id)
            ->assertHasErrors('cart')
            ->assertSet('cart', []);
    }

    public function test_receipts_are_limited_to_the_cashier_owner_or_authorized_supervisors(): void
    {
        [$cashier, $session] = $this->cashierWithTill();
        $otherCashier = $this->userWith(CommercePermission::ACCESS_POS);
        $supervisor = $this->userWith(CommercePermission::VIEW_ORDERS);
        $product = $this->product('RECEIPT-100', null, 8_000, 3);

        Livewire::actingAs($cashier)
            ->test(Terminal::class)
            ->call('addProduct', $product->id)
            ->set('tenders', [[
                'method' => PaymentMethod::Cash->value,
                'amount' => '80.00',
                'tendered' => '80.00',
                'reference' => '',
            ]])
            ->call('checkout');

        $order = Order::query()->where('till_session_id', $session->id)->sole();
        $receipt = route('commerce.pos.receipts.show', $order);
        $pdf = route('commerce.pos.receipts.pdf', $order);

        $this->actingAs($otherCashier)->get($receipt)->assertForbidden();
        $ownerResponse = $this->actingAs($cashier)->get($receipt);
        $ownerResponse->assertOk()->assertHeader('Cache-Control')->assertSee($order->order_number);
        $this->assertStringContainsString('no-store', (string) $ownerResponse->headers->get('Cache-Control'));
        $this->actingAs($supervisor)->get($receipt)->assertOk();
        $this->actingAs($cashier)->get($pdf)->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertDatabaseHas('system_activities', [
            'activity_type' => 'commerce.pos.receipt_printed',
            'subject_id' => $order->id,
        ]);
    }

    public function test_terminal_signals_a_beep_on_a_successful_add_when_sound_is_enabled(): void
    {
        config(['commerce.pos.sound.add_to_cart_enabled' => true]);
        [$cashier] = $this->cashierWithTill();
        $product = $this->product('BEEP-100', null, 5_000, 5);

        Livewire::actingAs($cashier)
            ->test(Terminal::class)
            ->call('addProduct', $product->id)
            ->assertHasNoErrors()
            ->assertDispatched('commerce-pos-cart-added');
    }

    public function test_terminal_stays_silent_when_the_add_to_cart_sound_is_disabled(): void
    {
        config(['commerce.pos.sound.add_to_cart_enabled' => false]);
        [$cashier] = $this->cashierWithTill();
        $product = $this->product('BEEP-200', null, 5_000, 5);

        Livewire::actingAs($cashier)
            ->test(Terminal::class)
            ->call('addProduct', $product->id)
            ->assertHasNoErrors()
            ->assertNotDispatched('commerce-pos-cart-added');
    }

    public function test_terminal_does_not_beep_when_the_add_fails(): void
    {
        config(['commerce.pos.sound.add_to_cart_enabled' => true]);
        [$cashier] = $this->cashierWithTill();
        $product = $this->product('BEEP-300', null, 5_000, 2);
        $product->forceFill(['minimum_order_quantity' => 3])->save();

        Livewire::actingAs($cashier)
            ->test(Terminal::class)
            ->call('addProduct', $product->id)
            ->assertHasErrors('cart')
            ->assertNotDispatched('commerce-pos-cart-added');
    }

    /** @return array{0: User, 1: TillSession} */
    private function cashierWithTill(): array
    {
        $cashier = $this->userWith(CommercePermission::ACCESS_POS);
        $session = app(TillService::class)->open(Register::factory()->create(), $cashier, 10_000);

        return [$cashier, $session];
    }

    private function userWith(string ...$permissions): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);

        return $user;
    }

    private function product(string $sku, ?string $barcode, int $priceMinor, int $stock): Product
    {
        return Product::factory()->published()->withStock($stock)->create([
            'sku' => $sku,
            'barcode' => $barcode,
            'price_minor' => $priceMinor,
            'sale_price_minor' => null,
        ]);
    }
}
