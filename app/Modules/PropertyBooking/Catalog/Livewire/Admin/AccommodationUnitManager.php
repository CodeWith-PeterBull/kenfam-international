<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Catalog\Livewire\Admin;

use App\Models\User;
use App\Modules\PropertyBooking\Catalog\Enums\UnitOperationalStatus;
use App\Modules\PropertyBooking\Catalog\Exceptions\CatalogException;
use App\Modules\PropertyBooking\Catalog\Livewire\Forms\AccommodationUnitForm;
use App\Modules\PropertyBooking\Catalog\Models\AccommodationUnit;
use App\Modules\PropertyBooking\Catalog\Models\Property;
use App\Modules\PropertyBooking\Catalog\Models\UnitType;
use App\Modules\PropertyBooking\Catalog\Services\AccommodationUnitService;
use App\Modules\PropertyBooking\Support\PropertyAccessService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Concrete unit CRUD, activation, archive, and operational-readiness workspace. */
final class AccommodationUnitManager extends Component
{
    use WithPagination;

    #[Url(as: 'unit-q', except: '')]
    public string $search = '';

    #[Url(as: 'unit-property', except: '')]
    public string $propertyFilter = '';

    #[Url(as: 'unit-type', except: '')]
    public string $unitTypeFilter = '';

    #[Url(as: 'unit-readiness', except: '')]
    public string $readinessFilter = '';

    public AccommodationUnitForm $form;

    public string $dialog = '';

    #[Locked]
    public ?int $selectedUnitId = null;

    private PropertyAccessService $access;

    /** Reauthorize and restore property scope for every Livewire request. */
    public function boot(PropertyAccessService $access): void
    {
        $this->access = $access;
        Gate::authorize('viewAny', AccommodationUnit::class);
    }

    /** Reset pagination when search changes. */
    public function updatedSearch(): void
    {
        $this->resetUnitPage();
    }

    /** Reset dependent filters and pagination when property changes. */
    public function updatedPropertyFilter(): void
    {
        if (! $this->unitTypeOptions->contains('id', (int) $this->unitTypeFilter)) {
            $this->unitTypeFilter = '';
        }
        $this->resetUnitPage();
    }

    /** Clear a stale unit type when a create form changes property. */
    public function updatedFormPropertyId(): void
    {
        if ($this->selectedUnitId !== null) {
            return;
        }

        $this->form->unitTypeId = '';
        unset($this->formUnitTypeOptions);
    }

    /** Reset pagination when unit type changes. */
    public function updatedUnitTypeFilter(): void
    {
        $this->resetUnitPage();
    }

    /** Reset pagination when readiness changes. */
    public function updatedReadinessFilter(): void
    {
        $this->resetUnitPage();
    }

    /** @return list<UnitOperationalStatus> */
    #[Computed]
    public function readinessStatuses(): array
    {
        return UnitOperationalStatus::cases();
    }

    /** @return Collection<int, Property> */
    #[Computed]
    public function propertyOptions(): Collection
    {
        return $this->access->scope(Property::query(), $this->actor(), 'property_booking_properties.id')
            ->orderBy('name')
            ->get(['id', 'name', 'code']);
    }

    /** @return Collection<int, UnitType> */
    #[Computed]
    public function unitTypeOptions(): Collection
    {
        return $this->access->scope(UnitType::query(), $this->actor(), 'property_booking_unit_types.property_id')
            ->when($this->propertyOptions->contains('id', (int) $this->propertyFilter), fn (Builder $query): Builder => $query->where('property_id', (int) $this->propertyFilter))
            ->orderBy('name')
            ->get(['id', 'property_id', 'name', 'code']);
    }

    /** @return Collection<int, UnitType> */
    #[Computed]
    public function formUnitTypeOptions(): Collection
    {
        if (! $this->propertyOptions->contains('id', (int) $this->form->propertyId)) {
            return collect();
        }

        return $this->access->scope(UnitType::query(), $this->actor(), 'property_booking_unit_types.property_id')
            ->where('property_id', (int) $this->form->propertyId)
            ->orderBy('name')
            ->get(['id', 'property_id', 'name', 'code']);
    }

    /** @return array{total: int, ready: int, attention: int, inactive: int} */
    #[Computed]
    public function statistics(): array
    {
        return [
            'total' => $this->scopedUnitQuery()->count(),
            'ready' => $this->scopedUnitQuery()->where('is_active', true)->where('operational_status', UnitOperationalStatus::Ready->value)->count(),
            'attention' => $this->scopedUnitQuery()->whereIn('operational_status', [UnitOperationalStatus::Dirty->value, UnitOperationalStatus::Cleaning->value, UnitOperationalStatus::Maintenance->value])->count(),
            'inactive' => $this->scopedUnitQuery()->where('is_active', false)->count(),
        ];
    }

    /** Return the scoped, filtered concrete-unit register. */
    #[Computed]
    public function units(): LengthAwarePaginator
    {
        $search = trim($this->search);

        return $this->scopedUnitQuery()
            ->when($search !== '', static function (Builder $query) use ($search): void {
                $query->where(static function (Builder $match) use ($search): void {
                    $match->where('code', 'like', "%{$search}%")
                        ->orWhere('display_name', 'like', "%{$search}%")
                        ->orWhere('floor_label', 'like', "%{$search}%");
                });
            })
            ->when($this->propertyOptions->contains('id', (int) $this->propertyFilter), fn (Builder $query): Builder => $query->where('property_id', (int) $this->propertyFilter))
            ->when($this->unitTypeOptions->contains('id', (int) $this->unitTypeFilter), fn (Builder $query): Builder => $query->where('unit_type_id', (int) $this->unitTypeFilter))
            ->when(UnitOperationalStatus::tryFrom($this->readinessFilter), static fn (Builder $query, UnitOperationalStatus $status): Builder => $query->where('operational_status', $status->value))
            ->with(['property', 'unitType'])
            ->withCount(['assignments', 'availabilityBlocks'])
            ->orderBy('property_id')
            ->orderBy('code')
            ->paginate(15, ['*'], 'unitPage');
    }

    /** Open an empty concrete-unit form. */
    public function openCreate(): void
    {
        Gate::authorize('create', AccommodationUnit::class);
        $this->closeDialog();
        $propertyId = $this->propertyOptions->contains('id', (int) $this->propertyFilter) ? (int) $this->propertyFilter : null;
        $this->form->resetForCreate($propertyId);
        $this->dialog = 'form';
    }

    /** Open one scoped unit for editing. */
    public function openEdit(int $unitId): void
    {
        $unit = $this->findUnit($unitId);
        Gate::authorize('update', $unit);
        $this->closeDialog();
        $this->selectedUnitId = $unit->getKey();
        $this->form->fillFromUnit($unit);
        $this->dialog = 'form';
    }

    /** Persist concrete-unit creation or update through its service. */
    public function save(AccommodationUnitService $units): void
    {
        $this->resetErrorBag('management');
        $this->form->validate();
        $actor = $this->actor();

        try {
            $property = $this->findProperty((int) $this->form->propertyId);
            $unitType = $this->findUnitType((int) $this->form->unitTypeId);
            if ($this->selectedUnitId === null) {
                Gate::authorize('create', AccommodationUnit::class);
                $this->access->authorize($actor, $property);
                $units->create($property, $unitType, $this->form->payload(), $actor);
                $message = 'Accommodation unit created.';
            } else {
                $unit = $this->findUnit($this->selectedUnitId);
                Gate::authorize('update', $unit);
                if (! $this->form->unit?->is($unit) || (int) $this->form->propertyId !== $unit->property_id) {
                    abort(404);
                }
                $units->update($unit, $unitType, $this->form->payload(), $actor);
                $message = 'Accommodation unit updated.';
            }
        } catch (CatalogException $exception) {
            $this->addError('management', $exception->getMessage());

            return;
        }

        $this->closeDialog();
        session()->flash('success', $message);
        $this->refreshUnits();
    }

    /** Activate or deactivate a concrete unit. */
    public function toggleActive(int $unitId, AccommodationUnitService $units): void
    {
        $unit = $this->findUnit($unitId);
        Gate::authorize('update', $unit);

        try {
            $units->setActive($unit, ! $unit->is_active, $this->actor());
        } catch (CatalogException $exception) {
            $this->addError('management', $exception->getMessage());

            return;
        }

        session()->flash('success', $unit->is_active ? 'Accommodation unit deactivated.' : 'Accommodation unit activated.');
        $this->refreshUnits();
    }

    /** Move a concrete unit through its readiness state machine. */
    public function changeReadiness(int $unitId, string $status, AccommodationUnitService $units): void
    {
        $unit = $this->findUnit($unitId);
        Gate::authorize('updateReadiness', $unit);
        $target = UnitOperationalStatus::tryFrom($status);
        abort_if($target === null, 422);

        try {
            $units->transitionReadiness($unit, $target, $this->actor());
        } catch (CatalogException $exception) {
            $this->addError('management', $exception->getMessage());

            return;
        }

        session()->flash('success', "Unit readiness changed to {$target->label()}.");
        $this->refreshUnits();
    }

    /** Archive an unused concrete unit while retaining history. */
    public function archive(int $unitId, AccommodationUnitService $units): void
    {
        $unit = $this->findUnit($unitId);
        Gate::authorize('delete', $unit);

        try {
            $units->archive($unit, $this->actor());
        } catch (CatalogException $exception) {
            $this->addError('management', $exception->getMessage());

            return;
        }

        session()->flash('success', 'Accommodation unit archived.');
        $this->refreshUnits();
    }

    /** Clear concrete-unit filters. */
    public function clearFilters(): void
    {
        $this->search = '';
        $this->propertyFilter = '';
        $this->unitTypeFilter = '';
        $this->readinessFilter = '';
        $this->resetUnitPage();
    }

    /** Close the form modal and clear validation state. */
    public function closeDialog(): void
    {
        $this->dialog = '';
        $this->selectedUnitId = null;
        $this->form->resetForCreate();
        $this->resetValidation();
    }

    /** Render the module-owned concrete-unit manager. */
    public function render(): View
    {
        return view('property-booking::livewire.admin.accommodation-unit-manager');
    }

    /** Build the authorized concrete-unit query. */
    private function scopedUnitQuery(): Builder
    {
        return $this->access->scope(AccommodationUnit::query(), $this->actor(), 'property_booking_units.property_id');
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

    /** Resolve one concrete unit through assigned scope. */
    private function findUnit(int $unitId): AccommodationUnit
    {
        return $this->scopedUnitQuery()->with(['property', 'unitType'])->findOrFail($unitId);
    }

    /** Resolve the authenticated actor. */
    private function actor(): User
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 401);

        return $actor;
    }

    /** Reset pagination and concrete-unit computed state. */
    private function resetUnitPage(): void
    {
        $this->resetPage('unitPage');
        $this->refreshUnits();
    }

    /** Invalidate concrete-unit derived computed state. */
    private function refreshUnits(): void
    {
        unset($this->units, $this->statistics, $this->unitTypeOptions, $this->formUnitTypeOptions);
    }
}
