<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Storefront\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\PropertyBooking\Catalog\Models\Property;
use App\Modules\PropertyBooking\Catalog\Models\UnitType;
use App\Modules\PropertyBooking\Storefront\Services\StorefrontNavigation;
use Illuminate\Contracts\View\View;

/** Presents published accommodation discovery and scoped detail surfaces. */
final class CatalogController extends Controller
{
    /** Create the controller with published navigation. */
    public function __construct(private readonly StorefrontNavigation $navigation) {}

    /** Render the public availability-search shell. */
    public function index(): View
    {
        return view('property-booking::storefront.catalog.index', $this->navigationData());
    }

    /** Render one published property resolved through a readable slug. */
    public function show(Property $property): View
    {
        abort_unless(Property::query()->published()->whereHas('category', static fn ($query) => $query->active())->whereKey($property->getKey())->exists(), 404);
        $property->load(['category', 'amenities', 'media', 'unitTypes' => static fn ($query) => $query->published()->with(['amenities', 'media'])]);

        return view('property-booking::storefront.catalog.show-property', [
            ...$this->navigationData(),
            'property' => $property,
        ]);
    }

    /** Render one published unit type scoped to its published parent property. */
    public function showUnit(Property $property, UnitType $unitType): View
    {
        abort_unless(
            Property::query()->published()->whereHas('category', static fn ($query) => $query->active())->whereKey($property->getKey())->exists()
            && UnitType::query()->published()->whereKey($unitType->getKey())->where('property_id', $property->getKey())->exists(),
            404,
        );
        $property->load(['category', 'amenities', 'media']);
        $unitType->load(['amenities', 'media', 'ratePlans' => static fn ($query) => $query->publiclyBookable()->orderBy('sort_order')]);

        return view('property-booking::storefront.catalog.show-unit', [
            ...$this->navigationData(),
            'property' => $property,
            'unitType' => $unitType,
        ]);
    }

    /** Return shared storefront navigation data. */
    private function navigationData(): array
    {
        return ['storefrontNavigationCategories' => $this->navigation->categories()];
    }
}
