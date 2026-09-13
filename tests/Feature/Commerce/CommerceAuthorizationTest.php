<?php

declare(strict_types=1);

namespace Tests\Feature\Commerce;

use App\Enums\UserType;
use App\Models\User;
use App\Modules\Commerce\Catalog\Models\Product;
use App\Modules\Commerce\Catalog\Models\ProductCategory;
use App\Modules\Commerce\Customers\Models\Customer;
use App\Modules\Commerce\Inventory\Models\Stock;
use App\Modules\Commerce\Inventory\Models\StockMovement;
use App\Modules\Commerce\Orders\Models\Order;
use App\Modules\Commerce\Orders\Models\Payment;
use App\Modules\Commerce\PointOfSale\Models\Register;
use App\Modules\Commerce\PointOfSale\Models\TillSession;
use App\Modules\Commerce\Support\CommercePermission;
use App\Support\CmsPermission;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Verifies Commerce capabilities are seeded and enforced by module policies.
 */
final class CommerceAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_commerce_permissions_join_the_shared_code_owned_catalogue(): void
    {
        $this->assertEqualsCanonicalizing(
            CommercePermission::all(),
            array_keys(array_filter(
                CmsPermission::catalogue(),
                static fn (array $definition): bool => $definition['group'] === 'Commerce',
            )),
        );

        foreach (CommercePermission::all() as $permission) {
            $this->assertTrue(Permission::findByName($permission)->exists);
            $this->assertTrue(Role::findByName(UserType::SystemAdministrator->value)->hasPermissionTo($permission));
        }
    }

    public function test_catalog_and_inventory_policies_separate_view_and_manage_actions(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $category = ProductCategory::factory()->create();
        $stock = Stock::factory()->for($product)->create();
        $movement = StockMovement::factory()->for($product)->create();

        $this->assertFalse(Gate::forUser($user)->allows('viewAny', Product::class));
        $user->givePermissionTo(CommercePermission::VIEW_PRODUCTS, CommercePermission::VIEW_INVENTORY);
        $this->assertTrue(Gate::forUser($user)->allows('viewAny', Product::class));
        $this->assertTrue(Gate::forUser($user)->allows('view', $category));
        $this->assertFalse(Gate::forUser($user)->allows('create', Product::class));
        $this->assertTrue(Gate::forUser($user)->allows('view', $stock));
        $this->assertTrue(Gate::forUser($user)->allows('view', $movement));
        $this->assertFalse(Gate::forUser($user)->allows('adjust', $stock));

        $user->givePermissionTo(CommercePermission::MANAGE_PRODUCTS, CommercePermission::MANAGE_INVENTORY);
        $this->assertTrue(Gate::forUser($user)->allows('create', Product::class));
        $this->assertTrue(Gate::forUser($user)->allows('update', $category));
        $this->assertTrue(Gate::forUser($user)->allows('adjust', $stock));
    }

    public function test_order_customer_and_pos_policies_require_their_specific_capabilities(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create();
        $payment = Payment::factory()->for($order)->create();
        $customer = Customer::factory()->create();
        $register = Register::factory()->create();
        $session = TillSession::factory()->for($register)->create();

        $user->givePermissionTo(CommercePermission::VIEW_ORDERS);
        $this->assertTrue(Gate::forUser($user)->allows('view', $order));
        $this->assertTrue(Gate::forUser($user)->allows('view', $payment));
        $this->assertFalse(Gate::forUser($user)->allows('update', $order));
        $this->assertFalse(Gate::forUser($user)->allows('view', $customer));
        $this->assertFalse(Gate::forUser($user)->allows('view', $register));

        $user->givePermissionTo(
            CommercePermission::MANAGE_ORDERS,
            CommercePermission::MANAGE_CUSTOMERS,
            CommercePermission::ACCESS_POS,
        );
        $this->assertTrue(Gate::forUser($user)->allows('cancel', $order));
        $this->assertTrue(Gate::forUser($user)->allows('record', Payment::class));
        $this->assertTrue(Gate::forUser($user)->allows('update', $customer));
        $this->assertTrue(Gate::forUser($user)->allows('view', $register));
        $this->assertTrue(Gate::forUser($user)->allows('view', $session));
        $this->assertFalse(Gate::forUser($user)->allows('close', $session));

        $user->givePermissionTo(CommercePermission::MANAGE_TILLS);
        $this->assertTrue(Gate::forUser($user)->allows('open', TillSession::class));
        $this->assertTrue(Gate::forUser($user)->allows('close', $session));
    }
}
