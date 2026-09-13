<?php

declare(strict_types=1);

namespace Tests\Feature\Commerce;

use App\Modules\Commerce\Catalog\Models\Product;
use App\Modules\Commerce\Catalog\Models\ProductCategory;
use App\Modules\Commerce\Customers\Models\Customer;
use App\Modules\Commerce\Inventory\Models\StockMovement;
use App\Modules\Commerce\Orders\Models\Order;
use App\Modules\Commerce\Orders\Models\Payment;
use App\Modules\Commerce\PointOfSale\Models\Register;
use App\Modules\Commerce\PointOfSale\Models\TillSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

/**
 * Verifies immutable separate-column ULIDs and implicit route model binding.
 */
final class CommerceUlidTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Ensure each externally addressable aggregate receives a unique valid ULID.
     */
    public function test_external_aggregates_generate_valid_unique_ulids(): void
    {
        $models = [
            ProductCategory::factory()->create(),
            Product::factory()->create(),
            Customer::factory()->create(),
            Register::factory()->create(),
            TillSession::factory()->create(),
            Order::factory()->create(),
            Payment::factory()->create(),
            StockMovement::factory()->create(),
        ];

        $ulids = collect($models)->pluck('ulid');

        $this->assertCount(count($models), $ulids->unique());
        foreach ($ulids as $ulid) {
            $this->assertTrue(Str::isUlid($ulid));
            $this->assertSame(26, strlen($ulid));
        }
    }

    /**
     * Ensure controlled factories may provide deterministic ULIDs while mass assignment cannot.
     */
    public function test_ulid_is_not_mass_assignable_but_factories_can_supply_a_valid_value(): void
    {
        $explicitUlid = (string) Str::ulid();
        $unpersisted = new Product(['ulid' => $explicitUlid, 'name' => 'Ignored ULID']);
        $persisted = Product::factory()->create(['ulid' => strtolower($explicitUlid)]);

        $this->assertNull($unpersisted->ulid);
        $this->assertSame(strtoupper($explicitUlid), $persisted->ulid);
    }

    /**
     * Ensure a persisted route identity cannot be changed.
     */
    public function test_ulid_is_immutable_after_creation(): void
    {
        $product = Product::factory()->create();
        $product->ulid = (string) Str::ulid();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Commerce ULID identifiers are immutable.');

        $product->save();
    }

    /**
     * Ensure administrative binding uses ULID and public catalog binding can use slug.
     */
    public function test_route_binding_uses_ulid_by_default_and_slug_when_explicit(): void
    {
        Route::middleware('web')->get('/_commerce-test/products/{product}', static fn (Product $product): string => $product->ulid);
        Route::middleware('web')->get('/_commerce-test/store/{product:slug}', static fn (Product $product): string => $product->slug);

        $product = Product::factory()->create();

        $this->get('/_commerce-test/products/'.$product->ulid)
            ->assertOk()
            ->assertSeeText($product->ulid);
        $this->get('/_commerce-test/products/'.$product->id)->assertNotFound();
        $this->get('/_commerce-test/store/'.$product->slug)
            ->assertOk()
            ->assertSeeText($product->slug);
    }
}
