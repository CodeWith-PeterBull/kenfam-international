<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Storefront\Services;

use App\Modules\PropertyBooking\Availability\Exceptions\AvailabilityException;
use App\Modules\PropertyBooking\Catalog\Models\Property;
use App\Modules\PropertyBooking\Catalog\Models\UnitType;
use App\Modules\PropertyBooking\Pricing\Exceptions\RateConfigurationException;
use App\Modules\PropertyBooking\Pricing\Models\RatePlan;
use App\Modules\PropertyBooking\Pricing\Services\BookingQuoteService;
use App\Modules\PropertyBooking\Storefront\Data\AvailableStayOption;
use App\Modules\PropertyBooking\Storefront\Data\PropertySearchResult;
use App\Modules\PropertyBooking\Storefront\Data\StaySearchData;
use App\Modules\PropertyBooking\Storefront\Exceptions\StorefrontException;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Throwable;

/** Produces availability-aware public property and rate projections. */
final readonly class StayDiscoveryService
{
    /** Create discovery from the side-effect-free quote service. */
    public function __construct(private BookingQuoteService $quotes) {}

    /** @return Collection<int, PropertySearchResult> */
    public function search(StaySearchData $search): Collection
    {
        $limit = max(1, min(100, (int) config('property-booking.storefront.catalog_limit', 24)));
        $properties = Property::query()
            ->published()
            ->whereHas('category', static fn (Builder $query): Builder => $query->active())
            ->when($search->categorySlug, static fn (Builder $query, string $slug): Builder => $query
                ->whereHas('category', static fn (Builder $category): Builder => $category->where('slug', $slug)->active()))
            ->when($search->location, function (Builder $query, string $location): Builder {
                $value = '%'.addcslashes(trim($location), '%_\\').'%';

                return $query->where(static fn (Builder $place): Builder => $place
                    ->where('city', 'like', $value)
                    ->orWhere('region', 'like', $value)
                    ->orWhere('country_code', 'like', $value)
                    ->orWhere('name', 'like', $value));
            })
            ->whereHas('unitTypes', static fn (Builder $query): Builder => $query
                ->published()
                ->fitsOccupancy($search->adults, $search->children, $search->infants)
                ->whereHas('ratePlans', static fn (Builder $rates): Builder => $rates->publiclyBookable()))
            ->with(['category.media', 'amenities', 'media', 'unitTypes' => static fn ($query) => $query
                ->published()
                ->fitsOccupancy($search->adults, $search->children, $search->infants)
                ->with(['amenities', 'media', 'ratePlans' => static fn ($rates) => $rates->publiclyBookable()->orderBy('sort_order')->orderBy('id')])])
            ->orderByDesc('is_featured')
            ->orderByDesc('published_at')
            ->orderBy('name')
            ->limit($limit)
            ->get();

        return $properties
            ->map(fn (Property $property): ?PropertySearchResult => $this->result($property, $search))
            ->filter()
            ->sortBy(static fn (PropertySearchResult $result): array => [! $result->property->is_featured, $result->minimumTotalMinor(), $result->property->name])
            ->values();
    }

    /** Return current options for one already-authorized public property. */
    public function result(Property $property, StaySearchData $search, ?UnitType $onlyUnitType = null): ?PropertySearchResult
    {
        [$startsAt, $endsAt] = $this->interval($property, $search);
        $property->loadMissing([
            'category.media', 'amenities', 'media',
            'unitTypes' => static fn ($query) => $query
                ->published()
                ->fitsOccupancy($search->adults, $search->children, $search->infants)
                ->with(['amenities', 'media', 'ratePlans' => static fn ($rates) => $rates->publiclyBookable()->orderBy('sort_order')->orderBy('id')]),
        ]);

        $types = $property->unitTypes;
        if ($onlyUnitType instanceof UnitType) {
            $types = $types->where('id', $onlyUnitType->getKey());
        }

        $options = $types->flatMap(function (UnitType $unitType) use ($startsAt, $endsAt, $search): array {
            return $unitType->ratePlans->map(function (RatePlan $ratePlan) use ($unitType, $startsAt, $endsAt, $search): ?AvailableStayOption {
                try {
                    $quote = $this->quotes->quote($ratePlan, $startsAt, $endsAt, $search->adults, $search->children, $search->infants);
                } catch (RateConfigurationException|AvailabilityException) {
                    return null;
                }

                return new AvailableStayOption($unitType, $ratePlan, $quote);
            })->filter()->values()->all();
        })->sortBy(static fn (AvailableStayOption $option): array => [$option->quote->calculation->totalMinor, $option->ratePlan->sort_order, $option->ratePlan->name])->values()->all();

        return $options === [] ? null : new PropertySearchResult($property, $startsAt, $endsAt, $options);
    }

    /** Convert guest-entered local dates and times into a property-specific UTC interval. */
    private function interval(Property $property, StaySearchData $search): array
    {
        try {
            $startsAt = CarbonImmutable::createFromFormat('!Y-m-d H:i', "{$search->arrivalDate} {$search->arrivalTime}", $property->timezone);
            $endsAt = CarbonImmutable::createFromFormat('!Y-m-d H:i', "{$search->departureDate} {$search->departureTime}", $property->timezone);
        } catch (Throwable $exception) {
            throw new StorefrontException('The selected arrival or departure time is invalid.', previous: $exception);
        }

        if (! $startsAt instanceof CarbonImmutable || ! $endsAt instanceof CarbonImmutable || $endsAt->lessThanOrEqualTo($startsAt)) {
            throw new StorefrontException('Departure must be after arrival.');
        }

        return [$startsAt->utc(), $endsAt->utc()];
    }
}
