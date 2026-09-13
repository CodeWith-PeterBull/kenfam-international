<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Pricing\Livewire\Admin;

use App\Models\User;
use App\Modules\PropertyBooking\Pricing\Exceptions\RateConfigurationException;
use App\Modules\PropertyBooking\Pricing\Livewire\Forms\RateOverrideForm;
use App\Modules\PropertyBooking\Pricing\Models\RateOverride;
use App\Modules\PropertyBooking\Pricing\Models\RatePlan;
use App\Modules\PropertyBooking\Pricing\Services\RateOverrideService;
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

/** Non-overlapping rate-override administration scoped through owning plans. */
final class RateOverrideManager extends Component
{
    use WithPagination;

    #[Url(as: 'override-rate', except: '')]
    public string $ratePlanFilter = '';

    public RateOverrideForm $form;

    public string $dialog = '';

    public string $targetRatePlanId = '';

    #[Locked]
    public ?int $selectedOverrideId = null;

    private PropertyAccessService $access;

    /** Reauthorize and restore property scope for every Livewire request. */
    public function boot(PropertyAccessService $access): void
    {
        $this->access = $access;
        Gate::authorize('viewAny', RateOverride::class);
    }

    /** Reset pagination when the owning plan changes. */
    public function updatedRatePlanFilter(): void
    {
        $this->resetOverridePage();
    }

    /** @return Collection<int, RatePlan> */
    #[Computed]
    public function ratePlanOptions(): Collection
    {
        return $this->access->scope(RatePlan::query(), $this->actor(), 'property_booking_rate_plans.property_id')
            ->with(['property:id,name', 'unitType:id,name'])
            ->orderBy('property_id')
            ->orderBy('name')
            ->get(['id', 'property_id', 'unit_type_id', 'name', 'code', 'currency']);
    }

    /** @return array{total: int, future: int, closures: int} */
    #[Computed]
    public function statistics(): array
    {
        return [
            'total' => $this->scopedOverrideQuery()->count(),
            'future' => $this->scopedOverrideQuery()->where('ends_on', '>', today()->toDateString())->count(),
            'closures' => $this->scopedOverrideQuery()->where('is_closed', true)->count(),
        ];
    }

    /** Return scoped and filtered rate overrides. */
    #[Computed]
    public function overrides(): LengthAwarePaginator
    {
        return $this->scopedOverrideQuery()
            ->when($this->ratePlanOptions->contains('id', (int) $this->ratePlanFilter), fn (Builder $query): Builder => $query->where('rate_plan_id', (int) $this->ratePlanFilter))
            ->with(['ratePlan.property', 'ratePlan.unitType'])
            ->orderByDesc('starts_on')
            ->orderByDesc('id')
            ->paginate(12, ['*'], 'overridePage');
    }

    /** Open an empty override form for an optional filtered rate plan. */
    public function openCreate(): void
    {
        Gate::authorize('create', RateOverride::class);
        $this->closeDialog();
        $this->targetRatePlanId = $this->ratePlanOptions->contains('id', (int) $this->ratePlanFilter)
            ? $this->ratePlanFilter
            : (string) ($this->ratePlanOptions->first()?->getKey() ?? '');
        $this->form->resetForCreate();
        $this->dialog = 'form';
    }

    /** Open one scoped override for editing. */
    public function openEdit(int $overrideId): void
    {
        $override = $this->findOverride($overrideId);
        Gate::authorize('update', $override);
        $this->closeDialog();
        $this->selectedOverrideId = $override->getKey();
        $this->targetRatePlanId = (string) $override->rate_plan_id;
        $this->form->fillFromOverride($override);
        $this->dialog = 'form';
    }

    /** Persist an override through its non-overlap service. */
    public function save(RateOverrideService $overrides): void
    {
        $this->resetErrorBag('management');
        $this->validate(['targetRatePlanId' => ['required', 'integer']]);
        $this->form->validate();
        $actor = $this->actor();

        try {
            if ($this->selectedOverrideId === null) {
                Gate::authorize('create', RateOverride::class);
                $ratePlan = $this->findRatePlan((int) $this->targetRatePlanId);
                $this->access->authorize($actor, $ratePlan->property_id);
                $overrides->create($ratePlan, $this->form->payload(), $actor);
                $message = 'Rate override created.';
            } else {
                $override = $this->findOverride($this->selectedOverrideId);
                Gate::authorize('update', $override);
                if (! $this->form->override?->is($override) || (int) $this->targetRatePlanId !== $override->rate_plan_id) {
                    abort(404);
                }
                $overrides->update($override, $this->form->payload(), $actor);
                $message = 'Rate override updated.';
            }
        } catch (RateConfigurationException|InvalidArgumentException $exception) {
            $this->addError('management', $exception->getMessage());

            return;
        }

        $this->closeDialog();
        session()->flash('success', $message);
        $this->refreshOverrides();
    }

    /** Delete one override after ownership and policy verification. */
    public function delete(int $overrideId, RateOverrideService $overrides): void
    {
        $override = $this->findOverride($overrideId);
        Gate::authorize('delete', $override);
        $overrides->delete($override, $this->actor());
        session()->flash('success', 'Rate override deleted.');
        $this->refreshOverrides();
    }

    /** Format an optional replacement rate. */
    public function money(?int $minor, string $currency): string
    {
        if ($minor === null) {
            return 'Base rate';
        }
        $decimals = max(0, min(6, (int) config('property-booking.defaults.currency_decimals', 2)));

        return strtoupper($currency).' '.number_format($minor / (10 ** $decimals), $decimals);
    }

    /** Clear the override plan filter. */
    public function clearFilters(): void
    {
        $this->ratePlanFilter = '';
        $this->resetOverridePage();
    }

    /** Close the override form and clear validation state. */
    public function closeDialog(): void
    {
        $this->dialog = '';
        $this->selectedOverrideId = null;
        $this->targetRatePlanId = '';
        $this->form->resetForCreate();
        $this->resetValidation();
    }

    /** Render the module-owned rate-override manager. */
    public function render(): View
    {
        return view('property-booking::livewire.admin.rate-override-manager');
    }

    /** Build a scoped override query through its owning rate plan. */
    private function scopedOverrideQuery(): Builder
    {
        $propertyIds = $this->access->assignedPropertyIds($this->actor());

        return RateOverride::query()->whereHas('ratePlan', static fn (Builder $query): Builder => $query->whereIn('property_id', $propertyIds));
    }

    /** Resolve one scoped rate plan. */
    private function findRatePlan(int $ratePlanId): RatePlan
    {
        return $this->access->scope(RatePlan::query(), $this->actor(), 'property_booking_rate_plans.property_id')->findOrFail($ratePlanId);
    }

    /** Resolve one scoped override. */
    private function findOverride(int $overrideId): RateOverride
    {
        return $this->scopedOverrideQuery()->with('ratePlan')->findOrFail($overrideId);
    }

    /** Resolve the authenticated actor. */
    private function actor(): User
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 401);

        return $actor;
    }

    /** Reset pagination and override computed state. */
    private function resetOverridePage(): void
    {
        $this->resetPage('overridePage');
        $this->refreshOverrides();
    }

    /** Invalidate override-derived computed state. */
    private function refreshOverrides(): void
    {
        unset($this->overrides, $this->statistics);
    }
}
