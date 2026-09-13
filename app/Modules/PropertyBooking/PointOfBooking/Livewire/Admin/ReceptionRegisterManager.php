<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\PointOfBooking\Livewire\Admin;

use App\Models\User;
use App\Modules\PropertyBooking\Catalog\Models\Property;
use App\Modules\PropertyBooking\PointOfBooking\Enums\ReceptionShiftStatus;
use App\Modules\PropertyBooking\PointOfBooking\Exceptions\ReceptionRegisterException;
use App\Modules\PropertyBooking\PointOfBooking\Livewire\Forms\ReceptionRegisterForm;
use App\Modules\PropertyBooking\PointOfBooking\Models\ReceptionRegister;
use App\Modules\PropertyBooking\PointOfBooking\Services\ReceptionRegisterService;
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

/** Property-scoped reception register configuration workspace. */
final class ReceptionRegisterManager extends Component
{
    use WithPagination;

    #[Url(as: 'register-q', except: '')]
    public string $search = '';

    #[Url(as: 'register-state', except: 'all')]
    public string $state = 'all';

    public string $dialog = '';

    #[Locked]
    public ?int $selectedRegisterId = null;

    public ReceptionRegisterForm $form;

    /** Authorize every hydration of this protected component. */
    public function boot(): void
    {
        Gate::authorize('viewAny', ReceptionRegister::class);
    }

    /** Reset pagination after a search change. */
    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /** Normalize the active-state filter and reset pagination. */
    public function updatedState(): void
    {
        if (! in_array($this->state, ['all', 'active', 'inactive'], true)) {
            $this->state = 'all';
        }
        $this->resetPage();
    }

    /** @return array{total:int, active:int, open:int} */
    #[Computed]
    public function statistics(): array
    {
        $query = $this->scopedQuery();

        return [
            'total' => (clone $query)->count(),
            'active' => (clone $query)->where('is_active', true)->count(),
            'open' => (clone $query)->whereHas('shifts', fn (Builder $shift): Builder => $shift->where('status', ReceptionShiftStatus::Open->value))->count(),
        ];
    }

    /** Return the property-scoped paginated register directory. */
    #[Computed]
    public function registers(): LengthAwarePaginator
    {
        return $this->scopedQuery()
            ->with(['property', 'shifts' => fn ($query) => $query
                ->where('status', ReceptionShiftStatus::Open->value)
                ->with('receptionist.profile')])
            ->withCount(['shifts', 'bookings'])
            ->when($this->state === 'active', fn (Builder $query): Builder => $query->where('is_active', true))
            ->when($this->state === 'inactive', fn (Builder $query): Builder => $query->where('is_active', false))
            ->when(trim($this->search) !== '', function (Builder $query): void {
                $search = '%'.trim($this->search).'%';
                $query->where(fn (Builder $match): Builder => $match
                    ->where('name', 'like', $search)
                    ->orWhere('code', 'like', $search)
                    ->orWhere('location_label', 'like', $search)
                    ->orWhereHas('property', fn (Builder $property): Builder => $property->where('name', 'like', $search)));
            })
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(12);
    }

    /** @return Collection<int, Property> */
    #[Computed]
    public function properties(): Collection
    {
        return app(PropertyAccessService::class)
            ->scope(Property::query(), $this->actor(), 'property_booking_properties.id')
            ->orderBy('name')
            ->get();
    }

    /** Open an empty register form. */
    public function openCreate(): void
    {
        Gate::authorize('create', ReceptionRegister::class);
        $this->closeDialog();
        $this->form->resetForCreate($this->properties->first()?->getKey());
        $this->dialog = 'form';
    }

    /** Open a register edit form after scoped authorization. */
    public function openEdit(int $registerId): void
    {
        $register = $this->findRegister($registerId);
        Gate::authorize('update', $register);
        $this->closeDialog();
        $this->selectedRegisterId = $register->getKey();
        $this->form->fillFromRegister($register);
        $this->dialog = 'form';
    }

    /** Persist the validated register through its domain service. */
    public function save(ReceptionRegisterService $registers): void
    {
        $this->form->validate();
        $actor = $this->actor();

        try {
            if ($this->selectedRegisterId === null) {
                Gate::authorize('create', ReceptionRegister::class);
                $property = $this->properties->firstWhere('id', (int) $this->form->propertyId);
                abort_unless($property instanceof Property, 403);
                $registers->create($property, $this->form->payload(), $actor);
                $message = 'Reception register created.';
            } else {
                $register = $this->findRegister($this->selectedRegisterId);
                Gate::authorize('update', $register);
                $registers->update($register, $this->form->payload(), $actor);
                $message = 'Reception register updated.';
            }
        } catch (ReceptionRegisterException $exception) {
            $this->addError('management', $exception->getMessage());

            return;
        }

        $this->closeDialog();
        session()->flash('success', $message);
        $this->refreshComputed();
    }

    /** Toggle register availability without deleting transaction history. */
    public function toggleActive(int $registerId, ReceptionRegisterService $registers): void
    {
        $register = $this->findRegister($registerId);
        Gate::authorize('update', $register);

        try {
            $updated = $registers->setActive($register, ! $register->is_active, $this->actor());
        } catch (ReceptionRegisterException $exception) {
            $this->addError('management', $exception->getMessage());

            return;
        }

        session()->flash('success', $updated->is_active ? 'Reception register activated.' : 'Reception register deactivated.');
        $this->refreshComputed();
    }

    /** Close the modal and clear transient form state. */
    public function closeDialog(): void
    {
        $this->dialog = '';
        $this->selectedRegisterId = null;
        $this->form->resetForCreate();
        $this->resetValidation();
    }

    /** Render the module-owned register manager. */
    public function render(): View
    {
        return view('property-booking::livewire.pob.admin.reception-register-manager');
    }

    /** Return a fresh property-scoped register query. */
    private function scopedQuery(): Builder
    {
        return app(PropertyAccessService::class)->scope(
            ReceptionRegister::query(),
            $this->actor(),
            'property_booking_registers.property_id',
        );
    }

    /** Resolve one register without permitting a cross-property identifier. */
    private function findRegister(int $registerId): ReceptionRegister
    {
        return $this->scopedQuery()->findOrFail($registerId);
    }

    /** Resolve the authenticated operator. */
    private function actor(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    /** Forget cached computed projections after mutation. */
    private function refreshComputed(): void
    {
        unset($this->registers, $this->statistics, $this->properties);
    }
}
