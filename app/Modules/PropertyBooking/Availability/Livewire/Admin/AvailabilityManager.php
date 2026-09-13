<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Availability\Livewire\Admin;

use App\Models\User;
use App\Modules\PropertyBooking\Availability\Enums\AvailabilityBlockStatus;
use App\Modules\PropertyBooking\Availability\Enums\AvailabilityBlockType;
use App\Modules\PropertyBooking\Availability\Livewire\Forms\AvailabilityBlockForm;
use App\Modules\PropertyBooking\Availability\Livewire\Forms\AvailabilitySearchForm;
use App\Modules\PropertyBooking\Availability\Models\AvailabilityBlock;
use App\Modules\PropertyBooking\Availability\Services\AvailabilityBlockService;
use App\Modules\PropertyBooking\Catalog\Models\AccommodationUnit;
use App\Modules\PropertyBooking\Catalog\Models\Property;
use App\Modules\PropertyBooking\Catalog\Models\UnitType;
use App\Modules\PropertyBooking\Exceptions\PropertyBookingException;
use App\Modules\PropertyBooking\Pricing\Enums\RatePlanStatus;
use App\Modules\PropertyBooking\Pricing\Models\RatePlan;
use App\Modules\PropertyBooking\Pricing\Services\BookingQuoteService;
use App\Modules\PropertyBooking\Support\PropertyAccessService;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Advisory quote search and auditable concrete-unit availability-block workspace. */
final class AvailabilityManager extends Component
{
    use WithPagination;

    #[Url(as: 'availability-property', except: '')]
    public string $propertyFilter = '';

    #[Url(as: 'block-status', except: 'active')]
    public string $statusFilter = 'active';

    public AvailabilitySearchForm $searchForm;

    public AvailabilityBlockForm $blockForm;

    /** @var array<string, mixed> */
    public array $quote = [];

    public string $dialog = '';

    #[Locked]
    public ?int $selectedBlockId = null;

    private PropertyAccessService $access;

    /** Reauthorize and restore property scope for every Livewire request. */
    public function boot(PropertyAccessService $access): void
    {
        $this->access = $access;
        Gate::authorize('viewAny', AvailabilityBlock::class);
    }

    /** Initialize property-local date defaults after the first authorized boot. */
    public function mount(): void
    {
        $property = $this->propertyOptions->first();
        $this->searchForm->resetForProperty($property);
        $this->blockForm->resetForProperty($property);
        $this->propertyFilter = $property === null ? '' : (string) $property->getKey();
    }

    /** Reset lists and block defaults when property changes. */
    public function updatedPropertyFilter(): void
    {
        $property = $this->propertyOptions->firstWhere('id', (int) $this->propertyFilter);
        $this->blockForm->resetForProperty($property);
        $this->resetPage('blockPage');
        $this->refreshAvailability();
    }

    /** Reset block pagination when status changes. */
    public function updatedStatusFilter(): void
    {
        if (! in_array($this->statusFilter, ['', AvailabilityBlockStatus::Active->value, AvailabilityBlockStatus::Released->value], true)) {
            $this->statusFilter = AvailabilityBlockStatus::Active->value;
        }
        $this->resetPage('blockPage');
        $this->refreshAvailability();
    }

    /** Clear dependent quote selectors when search property changes. */
    public function updatedSearchFormPropertyId(): void
    {
        $this->searchForm->unitTypeId = '';
        $this->searchForm->ratePlanId = '';
        $this->quote = [];
        unset($this->searchUnitTypeOptions, $this->searchRatePlanOptions);
    }

    /** Clear a stale rate selector when the search unit type changes. */
    public function updatedSearchFormUnitTypeId(): void
    {
        unset($this->searchRatePlanOptions);
        if (! $this->searchRatePlanOptions->contains('id', (int) $this->searchForm->ratePlanId)) {
            $this->searchForm->ratePlanId = '';
        }
        $this->quote = [];
    }

    /** @return Collection<int, Property> */
    #[Computed]
    public function propertyOptions(): Collection
    {
        return $this->access->scope(Property::query(), $this->actor(), 'property_booking_properties.id')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'timezone', 'currency']);
    }

    /** @return Collection<int, UnitType> */
    #[Computed]
    public function searchUnitTypeOptions(): Collection
    {
        if (! $this->propertyOptions->contains('id', (int) $this->searchForm->propertyId)) {
            return collect();
        }

        return $this->access->scope(UnitType::query(), $this->actor(), 'property_booking_unit_types.property_id')
            ->where('property_id', (int) $this->searchForm->propertyId)
            ->orderBy('name')
            ->get(['id', 'property_id', 'name', 'code', 'maximum_guests']);
    }

    /** @return Collection<int, RatePlan> */
    #[Computed]
    public function searchRatePlanOptions(): Collection
    {
        if (! $this->searchUnitTypeOptions->contains('id', (int) $this->searchForm->unitTypeId)) {
            return collect();
        }

        return $this->access->scope(RatePlan::query(), $this->actor(), 'property_booking_rate_plans.property_id')
            ->where('status', RatePlanStatus::Active->value)
            ->where('unit_type_id', (int) $this->searchForm->unitTypeId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'property_id', 'unit_type_id', 'name', 'code', 'currency', 'base_rate_minor']);
    }

    /** @return Collection<int, AccommodationUnit> */
    #[Computed]
    public function blockUnitOptions(): Collection
    {
        if (! $this->propertyOptions->contains('id', (int) $this->blockForm->propertyId)) {
            return collect();
        }

        return $this->access->scope(AccommodationUnit::query(), $this->actor(), 'property_booking_units.property_id')
            ->where('property_id', (int) $this->blockForm->propertyId)
            ->with('unitType:id,name')
            ->orderBy('code')
            ->get(['id', 'property_id', 'unit_type_id', 'code', 'display_name', 'is_active']);
    }

    /** @return list<AvailabilityBlockType> */
    #[Computed]
    public function blockTypes(): array
    {
        return AvailabilityBlockType::cases();
    }

    /** @return array{active: int, future: int, released: int, units: int} */
    #[Computed]
    public function statistics(): array
    {
        $query = $this->scopedBlockQuery();
        if ($this->propertyOptions->contains('id', (int) $this->propertyFilter)) {
            $query->where('property_id', (int) $this->propertyFilter);
        }
        $unitQuery = $this->access->scope(AccommodationUnit::query(), $this->actor(), 'property_booking_units.property_id');
        if ($this->propertyOptions->contains('id', (int) $this->propertyFilter)) {
            $unitQuery->where('property_id', (int) $this->propertyFilter);
        }

        return [
            'active' => (clone $query)->where('status', AvailabilityBlockStatus::Active->value)->count(),
            'future' => (clone $query)->where('status', AvailabilityBlockStatus::Active->value)->where('starts_at', '>', now())->count(),
            'released' => (clone $query)->where('status', AvailabilityBlockStatus::Released->value)->count(),
            'units' => $unitQuery->count(),
        ];
    }

    /** Return scoped block history. */
    #[Computed]
    public function blocks(): LengthAwarePaginator
    {
        return $this->scopedBlockQuery()
            ->when($this->propertyOptions->contains('id', (int) $this->propertyFilter), fn (Builder $query): Builder => $query->where('property_id', (int) $this->propertyFilter))
            ->when(AvailabilityBlockStatus::tryFrom($this->statusFilter), static fn (Builder $query, AvailabilityBlockStatus $status): Builder => $query->where('status', $status->value))
            ->with(['property', 'unit.unitType', 'creator', 'releaser'])
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->paginate(12, ['*'], 'blockPage');
    }

    /** Produce a side-effect-free advisory availability and price quote. */
    public function search(BookingQuoteService $quotes): void
    {
        $this->resetErrorBag('search');
        $this->searchForm->validate();

        try {
            $property = $this->findProperty((int) $this->searchForm->propertyId);
            $unitType = $this->findUnitType((int) $this->searchForm->unitTypeId);
            $ratePlan = $this->findRatePlan((int) $this->searchForm->ratePlanId);
            Gate::authorize('view', $unitType);
            Gate::authorize('view', $ratePlan);
            if ($unitType->property_id !== $property->getKey()
                || $ratePlan->property_id !== $property->getKey()
                || $ratePlan->unit_type_id !== $unitType->getKey()) {
                abort(404);
            }

            $quote = $quotes->quote(
                $ratePlan,
                $this->searchForm->startsAtUtc($property),
                $this->searchForm->endsAtUtc($property),
                $this->searchForm->adults,
                $this->searchForm->children,
                $this->searchForm->infants,
            );
            $this->quote = [
                'property' => $quote->propertyName,
                'unit_type' => $quote->unitTypeName,
                'rate_plan' => $quote->ratePlanName,
                'available_units' => $quote->availableUnitCount,
                'starts_at' => $quote->startsAt->setTimezone($property->timezone)->format('d M Y, H:i'),
                'ends_at' => $quote->endsAt->setTimezone($property->timezone)->format('d M Y, H:i'),
                'timezone' => $property->timezone,
                'currency' => $quote->calculation->currency,
                'billable_units' => $quote->calculation->billableUnits,
                'pricing_unit' => $quote->calculation->pricingUnit->label(),
                'subtotal_minor' => $quote->calculation->subtotalMinor,
                'tax_minor' => $quote->calculation->taxMinor,
                'total_minor' => $quote->calculation->totalMinor,
                'deposit_minor' => $quote->calculation->requiredDepositMinor,
                'expires_at' => $quote->expiresAt->setTimezone($property->timezone)->format('H:i:s'),
            ];
        } catch (PropertyBookingException|InvalidArgumentException $exception) {
            $this->quote = [];
            $this->addError('search', $exception->getMessage());
        }
    }

    /** Open an empty availability-block form. */
    public function openBlock(): void
    {
        Gate::authorize('create', AvailabilityBlock::class);
        $this->closeDialog();
        $property = $this->propertyOptions->firstWhere('id', (int) $this->propertyFilter) ?? $this->propertyOptions->first();
        $this->blockForm->resetForProperty($property);
        $this->dialog = 'block';
    }

    /** Persist one concrete-unit block through overlap-safe domain logic. */
    public function saveBlock(AvailabilityBlockService $blocks): void
    {
        $this->resetErrorBag('management');
        $this->blockForm->validate();
        $actor = $this->actor();

        try {
            $property = $this->findProperty((int) $this->blockForm->propertyId);
            $unit = $this->findUnit((int) $this->blockForm->unitId);
            Gate::authorize('create', AvailabilityBlock::class);
            $this->access->authorize($actor, $property);
            if ($unit->property_id !== $property->getKey()) {
                abort(404);
            }
            $blocks->create(
                $unit,
                AvailabilityBlockType::from($this->blockForm->type),
                $this->blockForm->startsAtUtc($property),
                $this->blockForm->endsAtUtc($property),
                $this->blockForm->reason,
                $actor,
                $this->blockForm->internalNote,
            );
        } catch (PropertyBookingException|InvalidArgumentException $exception) {
            $this->addError('management', $exception->getMessage());

            return;
        }

        $this->closeDialog();
        session()->flash('success', 'Availability block created.');
        $this->refreshAvailability();
    }

    /** Release one active block while retaining history. */
    public function release(int $blockId, AvailabilityBlockService $blocks): void
    {
        $block = $this->findBlock($blockId);
        Gate::authorize('update', $block);
        $blocks->release($block, $this->actor());
        session()->flash('success', 'Availability block released.');
        $this->refreshAvailability();
    }

    /** Format integer minor units for operator display. */
    public function money(int $minor, string $currency): string
    {
        $decimals = max(0, min(6, (int) config('property-booking.defaults.currency_decimals', 2)));

        return strtoupper($currency).' '.number_format($minor / (10 ** $decimals), $decimals);
    }

    /** Format a UTC model instant in its property's local timezone. */
    public function localDateTime(CarbonInterface $dateTime, string $timezone): string
    {
        return $dateTime->copy()->setTimezone($timezone)->format('d M Y, H:i');
    }

    /** Close the block modal and clear validation state. */
    public function closeDialog(): void
    {
        $this->dialog = '';
        $this->selectedBlockId = null;
        $property = $this->propertyOptions->firstWhere('id', (int) $this->propertyFilter);
        $this->blockForm->resetForProperty($property);
        $this->resetValidation();
    }

    /** Render the module-owned availability workspace. */
    public function render(): View
    {
        return view('property-booking::livewire.admin.availability-manager');
    }

    /** Build the authorized availability-block query. */
    private function scopedBlockQuery(): Builder
    {
        return $this->access->scope(AvailabilityBlock::query(), $this->actor(), 'property_booking_availability_blocks.property_id');
    }

    /** Resolve one property through assigned scope. */
    private function findProperty(int $propertyId): Property
    {
        return $this->access->scope(Property::query(), $this->actor(), 'property_booking_properties.id')->findOrFail($propertyId);
    }

    /** Resolve one unit type through assigned scope. */
    private function findUnitType(int $unitTypeId): UnitType
    {
        return $this->access->scope(UnitType::query(), $this->actor(), 'property_booking_unit_types.property_id')->findOrFail($unitTypeId);
    }

    /** Resolve one rate plan through assigned scope. */
    private function findRatePlan(int $ratePlanId): RatePlan
    {
        return $this->access->scope(RatePlan::query(), $this->actor(), 'property_booking_rate_plans.property_id')->findOrFail($ratePlanId);
    }

    /** Resolve one concrete unit through assigned scope. */
    private function findUnit(int $unitId): AccommodationUnit
    {
        return $this->access->scope(AccommodationUnit::query(), $this->actor(), 'property_booking_units.property_id')->findOrFail($unitId);
    }

    /** Resolve one block through assigned scope. */
    private function findBlock(int $blockId): AvailabilityBlock
    {
        return $this->scopedBlockQuery()->findOrFail($blockId);
    }

    /** Resolve the authenticated actor. */
    private function actor(): User
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 401);

        return $actor;
    }

    /** Invalidate all availability-derived computed state. */
    private function refreshAvailability(): void
    {
        unset($this->blocks, $this->statistics, $this->blockUnitOptions);
    }
}
