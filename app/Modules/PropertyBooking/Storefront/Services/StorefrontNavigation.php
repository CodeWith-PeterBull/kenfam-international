<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Storefront\Services;

use App\Modules\PropertyBooking\Catalog\Models\PropertyCategory;
use Illuminate\Support\Collection;

/** Resolves published accommodation navigation without operational records. */
final class StorefrontNavigation
{
    /** Return active categories containing at least one published property. */
    public function categories(): Collection
    {
        return PropertyCategory::query()
            ->active()
            ->whereHas('properties', static fn ($query) => $query->published())
            ->withCount(['properties as published_properties_count' => static fn ($query) => $query->published()])
            ->with('media')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }
}
