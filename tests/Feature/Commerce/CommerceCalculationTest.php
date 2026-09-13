<?php

declare(strict_types=1);

namespace Tests\Feature\Commerce;

use App\Modules\Commerce\Catalog\Models\Product;
use App\Modules\Commerce\Exceptions\InvalidCartException;
use App\Modules\Commerce\Orders\Data\CartItemData;
use App\Modules\Commerce\Orders\Data\CustomerSnapshotData;
use App\Modules\Commerce\Orders\Data\OrderPlacementData;
use App\Modules\Commerce\Orders\Enums\FulfillmentType;
use App\Modules\Commerce\Orders\Services\CartCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifies deterministic server-side Commerce pricing and integer arithmetic.
 */
final class CommerceCalculationTest extends TestCase
{
    use RefreshDatabase;

    public function test_inclusive_tax_and_fixed_discount_are_allocated_deterministically(): void
    {
        $first = Product::factory()->published()->create([
            'price_minor' => 10_000,
            'tax_rate_bps' => 1600,
            'is_tax_inclusive' => true,
        ]);
        $second = Product::factory()->published()->create([
            'price_minor' => 5_000,
            'tax_rate_bps' => 1600,
            'is_tax_inclusive' => true,
        ]);
        $data = $this->placement([$first->id => 1, $second->id => 1], discount: 1_001, delivery: 500);

        $calculation = app(CartCalculator::class)->calculate($data, collect([$second, $first]));

        $this->assertSame([$first->id, $second->id], array_map(
            static fn ($line): int => $line->product->id,
            $calculation->lines,
        ));
        $this->assertSame(15_000, $calculation->subtotalMinor);
        $this->assertSame(1_001, array_sum(array_map(static fn ($line): int => $line->discountMinor, $calculation->lines)));
        $this->assertSame(1_931, $calculation->taxMinor);
        $this->assertSame(14_499, $calculation->totalMinor);
        $this->assertTrue($calculation->taxInclusive);
    }

    public function test_exclusive_tax_is_added_after_discounted_line_value(): void
    {
        $product = Product::factory()->published()->create([
            'price_minor' => 10_000,
            'tax_rate_bps' => 1600,
            'is_tax_inclusive' => false,
        ]);

        $calculation = app(CartCalculator::class)->calculate(
            $this->placement([$product->id => 1]),
            collect([$product]),
        );

        $this->assertSame(1_600, $calculation->taxMinor);
        $this->assertSame(11_600, $calculation->totalMinor);
        $this->assertFalse($calculation->taxInclusive);
    }

    public function test_active_sale_price_is_recalculated_from_the_product(): void
    {
        $product = Product::factory()->published()->onSale(7_500)->create();

        $calculation = app(CartCalculator::class)->calculate(
            $this->placement([$product->id => 2]),
            collect([$product]),
        );

        $this->assertSame(7_500, $calculation->lines[0]->unitPriceMinor);
        $this->assertSame(15_000, $calculation->subtotalMinor);
    }

    public function test_unpublished_products_and_invalid_quantities_are_rejected(): void
    {
        $draft = Product::factory()->create(['minimum_order_quantity' => 2]);

        try {
            app(CartCalculator::class)->calculate(
                $this->placement([$draft->id => 1]),
                collect([$draft]),
            );
            $this->fail('A draft product was accepted for checkout.');
        } catch (InvalidCartException $exception) {
            $this->assertStringContainsString('not currently available', $exception->getMessage());
        }

        $published = Product::factory()->published()->create(['minimum_order_quantity' => 2]);
        $this->expectException(InvalidCartException::class);
        app(CartCalculator::class)->calculate(
            $this->placement([$published->id => 1]),
            collect([$published]),
        );
    }

    public function test_duplicate_products_are_rejected_before_calculation(): void
    {
        $product = Product::factory()->create();

        $this->expectException(InvalidCartException::class);
        new OrderPlacementData(
            items: [new CartItemData($product->id, 1), new CartItemData($product->id, 2)],
            customer: $this->customer(),
            fulfillmentType: FulfillmentType::Pickup,
        );
    }

    /**
     * @param  array<int, int>  $quantities
     */
    private function placement(array $quantities, int $discount = 0, int $delivery = 0): OrderPlacementData
    {
        return new OrderPlacementData(
            items: array_map(
                static fn (int $quantity, int $productId): CartItemData => new CartItemData($productId, $quantity),
                array_values($quantities),
                array_keys($quantities),
            ),
            customer: $this->customer(),
            fulfillmentType: $delivery > 0 ? FulfillmentType::Delivery : FulfillmentType::Pickup,
            discountMinor: $discount,
            deliveryFeeMinor: $delivery,
        );
    }

    private function customer(): CustomerSnapshotData
    {
        return new CustomerSnapshotData(
            firstName: 'Amina',
            lastName: 'Otieno',
            email: 'amina@example.test',
            addressLine1: '14 Riverside Drive',
            city: 'Nairobi',
        );
    }
}
