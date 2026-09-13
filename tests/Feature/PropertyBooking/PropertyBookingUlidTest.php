<?php

declare(strict_types=1);

namespace Tests\Feature\PropertyBooking;

use App\Modules\PropertyBooking\Catalog\Models\Property;
use App\Modules\PropertyBooking\Catalog\Models\PropertyCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

/** Verifies separate, immutable, externally safe Property Booking ULIDs. */
final class PropertyBookingUlidTest extends TestCase
{
    use RefreshDatabase;

    public function test_route_models_generate_unique_valid_ulids(): void
    {
        $models = [PropertyCategory::factory()->create(), Property::factory()->create()];

        $this->assertCount(2, collect($models)->pluck('ulid')->unique());
        foreach ($models as $model) {
            $this->assertTrue(Str::isUlid($model->ulid));
            $this->assertSame('ulid', $model->getRouteKeyName());
        }
    }

    public function test_ulid_is_not_mass_assignable_but_factory_input_is_normalized(): void
    {
        $ulid = (string) Str::ulid();
        $unpersisted = new PropertyCategory(['ulid' => $ulid, 'name' => 'Ignored']);
        $persisted = PropertyCategory::factory()->create(['ulid' => strtolower($ulid)]);

        $this->assertNull($unpersisted->ulid);
        $this->assertSame(strtoupper($ulid), $persisted->ulid);
    }

    public function test_persisted_ulid_is_immutable(): void
    {
        $property = Property::factory()->create();
        $property->ulid = (string) Str::ulid();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Property Booking ULID identifiers are immutable.');
        $property->save();
    }
}
