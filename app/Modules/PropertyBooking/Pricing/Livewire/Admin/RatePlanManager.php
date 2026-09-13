<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Pricing\Livewire\Admin;

use App\Models\User;
use App\Modules\PropertyBooking\Catalog\Models\Property;
use App\Modules\PropertyBooking\Catalog\Models\UnitType;
use App\Modules\PropertyBooking\Pricing\Enums\DepositType;
use App\Modules\PropertyBooking\Pricing\Enums\RatePlanStatus;
use App\Modules\PropertyBooking\Pricing\Enums\StayPricingUnit;
use App\Modules\PropertyBooking\Pricing\Exceptions\RateConfigurationException;
use App\Modules\PropertyBooking\Pricing\Livewire\Forms\RatePlanForm;
use App\Modules\PropertyBooking\Pricing\Models\RatePlan;
use App\Modules\PropertyBooking\Pricing\Services\RatePlanService;
use App\Modules\PropertyBooking\Support\PropertyAccessService;
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

/** Property-scoped rate-plan CRUD, lifecycle, and pricing-policy administration. */
final class RatePlanManager extends Component
{
    use WithPagination;

    #[Url(as: 'rate-q', except: '')]
    public string $search = '';

    #[Url(as: 'rate-property', except: '')]
    public string $propertyFilter = '';

    #[Url(as: 'rate-status', except: '')]
    public string $statusFilter = '';

    public RatePlanForm $form;

    public string $dialog = '';

    #[Locked]
    public ?int $selectedRatePlanId = null;

    private PropertyAccessService $access;

    /** Reauthorize and restore property scope for every Livewire request. */
    public function boot(PropertyAccessService $access): void
    {
        $this->access = $access;
        Gate::authorize('viewAny', RatePlan::class);
    }

    /** Reset pagination when search changes. */
    public function updatedSearch(): void
    {
        $this->resetRatePage();
    }

    /** Reset pagination when property changes. */
    public function updatedPropertyFilter(): void
    {
        $this->resetRatePage();
    }

    /** Reset pagination when lifecycle state changes. */
    public function updatedStatusFilter(): void
    {
        $this->resetRatePage();
    }

    /** @return list<RatePlanStatus> */
    #[Computed]
    public function statuses(): array
    {
        return RatePlanStatus::cases();
    }

    /** @return list<StayPricingUnit> */
    #[Computed]
    public function pricingUnits(): array
    {
        return StayPricingUnit::cases();
    }

    /** @return list<DepositType> */
    #[Computed]
    public function depositTypes(): array
    {
        return DepositType::cases();
    }

    /** @return Collection<int, Property> */
    #[Computed]
    public function propertyOptions(): Collection
    {
        return $this->access->scope(Property::query(), $this->actor(), 'property_booking_properties.id')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'currency']);
    }

    /** @return Collection<int, UnitType> */
    #[Computed]
    public function unitTypeOptions(): Collection
    {
        return $this->access->scope(UnitType::query(), $this->actor(), 'property_booking_unit_types.property_id')
            ->with('property:id,currency')
            ->when($this->propertyOptions->contains('id', (int) $this->propertyFilter), fn (Builder $query): Builder => $query->where('property_id', (int) $this->propertyFilter))
            ->orderBy('name')
            ->get(['id', 'property_id', 'name', 'code']);
    }

    /** @return array{total: int, active: int, draft: int, public: int} */
    #[Computed]
    public function statistics(): array
    {
        return [
            'total' => $this->scopedRateQuery()->count(),
            'active' => $this->scopedRateQuery()->where('status', RatePlanStatus::Active->value)->count(),
            'draft' => $this->scopedRateQuery()->where('status', RatePlanStatus::Draft->value)->count(),
            'public' => $this->scopedRateQuery()->where('is_public', true)->count(),
        ];
    }

    /** Return the scoped and filtered rate register. */
    #[Computed]
    public function ratePlans(): LengthAwarePaginator
    {
        $search = trim($this->search);

        return $this->scopedRateQuery()
            ->when($search !== '', static function (Builder $query) use ($search): void {
                $query->where(static function (Builder $match) use ($search): void {
                    $match->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->when($this->propertyOptions->contains('id', (int) $this->propertyFilter), fn (Builder $query): Builder => $query->where('property_id', (int) $this->propertyFilter))
            ->when(RatePlanStatus::tryFrom($this->statusFilter), static fn (Builder $query, RatePlanStatus $status): Builder => $query->where('status', $status->value))
            ->with(['property', 'unitType'])
            ->withCount('overrides')
            ->orderBy('property_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(15, ['*'], 'ratePage');
    }

    /** Open an empty rate-plan form. */
    public function openCreate(): void
    {
        Gate::authorize('create', RatePlan::class);
        $this->closeDialog();
        $unitType = $this->unitTypeOptions->first();
        $this->form->resetForCreate($unitType?->getKey(), $unitType?->property?->currency);
        $this->dialog = 'form';
    }

    /** Open one scoped rate plan for editing. */
    public function openEdit(int $ratePlanId): void
    {
        $ratePlan = $this->findRatePlan($ratePlanId);
        Gate::authorize('update', $ratePlan);
        $this->closeDialog();
        $this->selectedRatePlanId = $ratePlan->getKey();
        $this->form->fillFromRatePlan($ratePlan);
        $this->dialog = 'form';
    }

    /** Synchronize currency when a new form changes unit type. */
    public function updatedFormUnitTypeId(): void
    {
        if ($this->selectedRatePlanId !== null) {
            return;
        }
        $unitType = $this->unitTypeOptions->firstWhere('id', (int) $this->form->unitTypeId);
        if ($unitType instanceof UnitType) {
            $this->form->currency = $unitType->property->currency;
        }
    }

    /** Persist a rate plan through its domain service. */
    public function save(RatePlanService $rates): void
    {
        $this->resetErrorBag('management');
        $this->form->validate();
        $actor = $this->actor();

        try {
            if ($this->selectedRatePlanId === null) {
                Gate::authorize('create', RatePlan::class);
                $unitType = $this->findUnitType((int) $this->form->unitTypeId);
                $this->access->authorize($actor, $unitType->property_id);
                $rates->create($unitType, $this->form->payload(), $actor);
                $message = 'Rate plan created as a draft.';
            } else {
                $ratePlan = $this->findRatePlan($this->selectedRatePlanId);
                Gate::authorize('update', $ratePlan);
                if (! $this->form->ratePlan?->is($ratePlan) || (int) $this->form->unitTypeId !== $ratePlan->unit_type_id) {
                    abort(404);
                }
                $rates->update($ratePlan, $this->form->payload(), $actor);
                $message = 'Rate-plan details updated.';
            }
        } catch (RateConfigurationException|InvalidArgumentException $exception) {
            $this->addError('management', $exception->getMessage());

            return;
        }

        $this->closeDialog();
        session()->flash('success', $message);
        $this->refreshRates();
    }

    /** Move a rate plan through its controlled lifecycle. */
    public function changeStatus(int $ratePlanId, string $status, RatePlanService $rates): void
    {
        $ratePlan = $this->findRatePlan($ratePlanId);
        Gate::authorize('update', $ratePlan);
        $target = RatePlanStatus::tryFrom($status);
        abort_if($target === null, 422);

        try {
            $rates->transition($ratePlan, $target, $this->actor());
        } catch (RateConfigurationException $exception) {
            $this->addError('management', $exception->getMessage());

            return;
        }

        session()->flash('success', "Rate-plan status changed to {$target->label()}.");
        $this->refreshRates();
    }

    /** Format integer minor units for operator display. */
    public function money(int $minor, string $currency): string
    {
        $decimals = max(0, min(6, (int) config('property-booking.defaults.currency_decimals', 2)));

        return strtoupper($currency).' '.number_format($minor / (10 ** $decimals), $decimals);
    }

    /** Clear rate filters. */
    public function clearFilters(): void
    {
        $this->search = '';
        $this->propertyFilter = '';
        $this->statusFilter = '';
        $this->resetRatePage();
    }

    /** Close the rate form and clear validation state. */
    public function closeDialog(): void
    {
        $this->dialog = '';
        $this->selectedRatePlanId = null;
        $this->form->resetForCreate();
        $this->resetValidation();
    }

    /** Render the module-owned rate-plan manager. */
    public function render(): View
    {
        return view('property-booking::livewire.admin.rate-plan-manager');
    }

    /** Build the authorized rate-plan query. */
    private function scopedRateQuery(): Builder
    {
        return $this->access->scope(RatePlan::query(), $this->actor(), 'property_booking_rate_plans.property_id');
    }

    /** Resolve one unit type through assigned property scope. */
    private function findUnitType(int $unitTypeId): UnitType
    {
        return $this->access->scope(UnitType::query(), $this->actor(), 'property_booking_unit_types.property_id')
            ->with('property')
            ->findOrFail($unitTypeId);
    }

    /** Resolve one rate plan through assigned property scope. */
    private function findRatePlan(int $ratePlanId): RatePlan
    {
        return $this->scopedRateQuery()->with(['property', 'unitType'])->findOrFail($ratePlanId);
    }

    /** Resolve the authenticated actor. */
    private function actor(): User
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 401);

        return $actor;
    }

    /** Reset pagination and rate-plan computed state. */
    private function resetRatePage(): void
    {
        $this->resetPage('ratePage');
        $this->refreshRates();
    }

    /** Invalidate rate-plan derived computed state. */
    private function refreshRates(): void
    {
        unset($this->ratePlans, $this->statistics, $this->unitTypeOptions);
    }
}
