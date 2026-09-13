<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Operations\Livewire\Admin;

use App\Models\User;
use App\Modules\PropertyBooking\Bookings\Enums\StayStatus;
use App\Modules\PropertyBooking\Bookings\Enums\UnitAssignmentStatus;
use App\Modules\PropertyBooking\Catalog\Enums\UnitOperationalStatus;
use App\Modules\PropertyBooking\Catalog\Exceptions\CatalogException;
use App\Modules\PropertyBooking\Catalog\Models\AccommodationUnit;
use App\Modules\PropertyBooking\Catalog\Models\Property;
use App\Modules\PropertyBooking\Catalog\Services\AccommodationUnitService;
use App\Modules\PropertyBooking\Support\PropertyAccessService;
use App\Modules\PropertyBooking\Support\PropertyBookingPermission;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Housekeeping workspace limited to unit occupancy context and readiness transitions. */
final class UnitReadinessManager extends Component
{
    use WithPagination;

    #[Url(as: 'readiness-q', except: '')]
    public string $search = '';

    #[Url(as: 'readiness-property', except: '')]
    public string $propertyFilter = '';

    #[Url(as: 'readiness-state', except: '')]
    public string $statusFilter = '';

    #[Url(as: 'readiness-occupancy', except: '')]
    public string $occupancyFilter = '';

    #[Url(as: 'readiness-per-page', except: 15)]
    public int $perPage = 15;

    private PropertyAccessService $access;

    /** Reauthorize readiness access on every Livewire request. */
    public function boot(PropertyAccessService $access): void
    {
        $this->access = $access;
        Gate::authorize('viewAny', AccommodationUnit::class);
        abort_unless($this->actor()->can(PropertyBookingPermission::MANAGE_READINESS), 403);
    }

    /** Reset pagination when the unit search changes. */
    public function updatedSearch(): void
    {
        $this->resetReadinessPage();
    }

    /** Validate property scope and reset pagination. */
    public function updatedPropertyFilter(): void
    {
        if (! $this->propertyOptions->contains('id', (int) $this->propertyFilter)) {
            $this->propertyFilter = '';
        }
        $this->resetReadinessPage();
    }

    /** Normalize the readiness-state filter. */
    public function updatedStatusFilter(): void
    {
        $this->statusFilter = UnitOperationalStatus::tryFrom($this->statusFilter)?->value ?? '';
        $this->resetReadinessPage();
    }

    /** Normalize the derived occupancy filter. */
    public function updatedOccupancyFilter(): void
    {
        $this->occupancyFilter = in_array($this->occupancyFilter, ['occupied', 'reserved', 'vacant'], true)
            ? $this->occupancyFilter
            : '';
        $this->resetReadinessPage();
    }

    /** Restrict and apply the requested page size. */
    public function updatedPerPage(): void
    {
        $this->perPage = in_array($this->perPage, [10, 15, 25, 50], true) ? $this->perPage : 15;
        $this->resetReadinessPage();
    }

    /** @return Collection<int, Property> */
    #[Computed]
    public function propertyOptions(): Collection
    {
        return $this->access
            ->scope(Property::query(), $this->actor(), 'property_booking_properties.id')
            ->orderBy('name')
            ->get(['id', 'name', 'code']);
    }

    /** @return list<UnitOperationalStatus> */
    #[Computed]
    public function statuses(): array
    {
        return UnitOperationalStatus::cases();
    }

    /** @return array{ready: int, dirty: int, cleaning: int, unavailable: int} */
    #[Computed]
    public function statistics(): array
    {
        return [
            'ready' => $this->scopedUnitQuery()->where('operational_status', UnitOperationalStatus::Ready->value)->count(),
            'dirty' => $this->scopedUnitQuery()->where('operational_status', UnitOperationalStatus::Dirty->value)->count(),
            'cleaning' => $this->scopedUnitQuery()->where('operational_status', UnitOperationalStatus::Cleaning->value)->count(),
            'unavailable' => $this->scopedUnitQuery()->whereIn('operational_status', [
                UnitOperationalStatus::Maintenance->value,
                UnitOperationalStatus::OutOfService->value,
            ])->count(),
        ];
    }

    /** Return the scoped readiness board with minimum occupancy context. */
    #[Computed]
    public function units(): LengthAwarePaginator
    {
        $search = trim($this->search);

        return $this->scopedUnitQuery()
            ->when($search !== '', static function (Builder $query) use ($search): void {
                $query->where(static fn (Builder $match): Builder => $match
                    ->where('code', 'like', "%{$search}%")
                    ->orWhere('display_name', 'like', "%{$search}%")
                    ->orWhere('floor_label', 'like', "%{$search}%"));
            })
            ->when($this->propertyOptions->contains('id', (int) $this->propertyFilter), fn (Builder $query): Builder => $query->where('property_id', (int) $this->propertyFilter))
            ->when(UnitOperationalStatus::tryFrom($this->statusFilter), static fn (Builder $query, UnitOperationalStatus $status): Builder => $query->where('operational_status', $status->value))
            ->when($this->occupancyFilter === 'occupied', static fn (Builder $query): Builder => $query->whereHas('assignments', static fn (Builder $assignment): Builder => $assignment
                ->where('status', UnitAssignmentStatus::Active->value)
                ->whereHas('booking', static fn (Builder $booking): Builder => $booking->where('stay_status', StayStatus::CheckedIn->value))))
            ->when($this->occupancyFilter === 'reserved', static fn (Builder $query): Builder => $query->whereHas('assignments', static fn (Builder $assignment): Builder => $assignment
                ->where('status', UnitAssignmentStatus::Active->value)
                ->whereHas('booking', static fn (Builder $booking): Builder => $booking->where('stay_status', StayStatus::Expected->value))))
            ->when($this->occupancyFilter === 'vacant', static fn (Builder $query): Builder => $query->whereDoesntHave('assignments', static fn (Builder $assignment): Builder => $assignment->where('status', UnitAssignmentStatus::Active->value)))
            ->with(['property:id,name,code', 'unitType:id,name,code', 'assignments' => static fn ($query) => $query
                ->where('status', UnitAssignmentStatus::Active->value)
                ->with('booking:id,booking_number,stay_status,guest_first_name,guest_last_name,starts_at,ends_at,property_timezone')])
            ->orderByRaw("CASE operational_status WHEN 'dirty' THEN 0 WHEN 'cleaning' THEN 1 WHEN 'maintenance' THEN 2 WHEN 'out_of_service' THEN 3 ELSE 4 END")
            ->orderBy('property_id')
            ->orderBy('code')
            ->paginate($this->perPage, ['*'], 'readinessPage');
    }

    /** Advance one unit through an allowed, service-validated readiness transition. */
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

        session()->flash('success', "{$unit->code} moved to {$target->label()}.");
        $this->refreshUnits();
    }

    /** Reset every readiness filter and page cursor. */
    public function clearFilters(): void
    {
        $this->search = '';
        $this->propertyFilter = '';
        $this->statusFilter = '';
        $this->occupancyFilter = '';
        $this->resetReadinessPage();
    }

    /** Render the unit-readiness component. */
    public function render(): View
    {
        return view('property-booking::livewire.admin.unit-readiness-manager');
    }

    /** Build the active-unit query inside operator property scope. */
    private function scopedUnitQuery(): Builder
    {
        return $this->access->scope(
            AccommodationUnit::query()->where('is_active', true),
            $this->actor(),
            'property_booking_units.property_id',
        );
    }

    /** Resolve one unit through the scoped query. */
    private function findUnit(int $unitId): AccommodationUnit
    {
        return $this->scopedUnitQuery()->findOrFail($unitId);
    }

    /** Resolve the authenticated readiness operator. */
    private function actor(): User
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 401);

        return $actor;
    }

    /** Reset pagination and invalidate unit projections. */
    private function resetReadinessPage(): void
    {
        $this->resetPage('readinessPage');
        $this->refreshUnits();
    }

    /** Invalidate readiness-related computed projections. */
    private function refreshUnits(): void
    {
        unset($this->units, $this->statistics);
    }
}
