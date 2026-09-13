<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\PointOfBooking\Livewire\Admin;

use App\Models\User;
use App\Modules\PropertyBooking\PointOfBooking\Enums\ReceptionShiftStatus;
use App\Modules\PropertyBooking\PointOfBooking\Exceptions\ReceptionShiftException;
use App\Modules\PropertyBooking\PointOfBooking\Livewire\Forms\ReceptionShiftCloseForm;
use App\Modules\PropertyBooking\PointOfBooking\Livewire\Forms\ReceptionShiftOpenForm;
use App\Modules\PropertyBooking\PointOfBooking\Models\ReceptionRegister;
use App\Modules\PropertyBooking\PointOfBooking\Models\ReceptionShift;
use App\Modules\PropertyBooking\PointOfBooking\Services\ReceptionShiftService;
use App\Modules\PropertyBooking\Support\MoneyFormatter;
use App\Modules\PropertyBooking\Support\PropertyAccessService;
use App\Modules\PropertyBooking\Support\PropertyBookingPermission;
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

/** Opens, monitors, reconciles, and closes property-scoped reception shifts. */
final class ReceptionShiftManager extends Component
{
    use WithPagination;

    #[Url(as: 'shift-q', except: '')]
    public string $search = '';

    #[Url(as: 'shift-state', except: 'open')]
    public string $state = 'open';

    public string $dialog = '';

    #[Locked]
    public ?int $selectedShiftId = null;

    public ReceptionShiftOpenForm $openForm;

    public ReceptionShiftCloseForm $closeForm;

    /** Authorize every hydration of this protected component. */
    public function boot(): void
    {
        Gate::authorize('viewAny', ReceptionShift::class);
    }

    /** Reset pagination after a search change. */
    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /** Normalize the state filter and reset pagination. */
    public function updatedState(): void
    {
        if (! in_array($this->state, ['open', 'closed', 'all'], true)) {
            $this->state = 'open';
        }
        $this->resetPage();
    }

    /** Refresh receptionist options when a register is selected. */
    public function updatedOpenFormRegisterId(): void
    {
        $this->openForm->receptionistId = '';
        unset($this->availableReceptionists);
    }

    /** @return array{open:int, closed_today:int, expected_cash_minor:int, variance_minor:int} */
    #[Computed]
    public function statistics(): array
    {
        $query = $this->scopedQuery();

        return [
            'open' => (clone $query)->where('status', ReceptionShiftStatus::Open->value)->count(),
            'closed_today' => (clone $query)->where('status', ReceptionShiftStatus::Closed->value)->whereDate('closed_at', today())->count(),
            'expected_cash_minor' => (int) (clone $query)->where('status', ReceptionShiftStatus::Open->value)->sum('expected_cash_minor'),
            'variance_minor' => (int) (clone $query)->whereDate('closed_at', today())->sum('variance_minor'),
        ];
    }

    /** Return paginated shift history within the operator property scope. */
    #[Computed]
    public function shifts(): LengthAwarePaginator
    {
        return $this->scopedQuery()
            ->with(['property', 'register', 'receptionist.profile', 'opener.profile', 'closer.profile'])
            ->withCount(['bookings', 'payments'])
            ->when($this->state !== 'all', fn (Builder $query): Builder => $query->where('status', $this->state))
            ->when(trim($this->search) !== '', function (Builder $query): void {
                $search = '%'.trim($this->search).'%';
                $query->where(fn (Builder $match): Builder => $match
                    ->whereHas('register', fn (Builder $register): Builder => $register
                        ->where('name', 'like', $search)->orWhere('code', 'like', $search))
                    ->orWhereHas('property', fn (Builder $property): Builder => $property->where('name', 'like', $search))
                    ->orWhereHas('receptionist', fn (Builder $user): Builder => $user
                        ->where('name', 'like', $search)->orWhere('email', 'like', $search)));
            })
            ->latest('opened_at')
            ->paginate(12);
    }

    /** @return Collection<int, ReceptionRegister> */
    #[Computed]
    public function availableRegisters(): Collection
    {
        return app(PropertyAccessService::class)->scope(
            ReceptionRegister::query(),
            $this->actor(),
            'property_booking_registers.property_id',
        )
            ->active()
            ->whereDoesntHave('shifts', fn (Builder $query): Builder => $query->where('status', ReceptionShiftStatus::Open->value))
            ->with('property')
            ->orderBy('name')
            ->get();
    }

    /** @return Collection<int, User> */
    #[Computed]
    public function availableReceptionists(): Collection
    {
        $register = $this->availableRegisters->firstWhere('id', (int) $this->openForm->registerId);
        if (! $register instanceof ReceptionRegister) {
            return collect();
        }
        $busyIds = ReceptionShift::query()->where('status', ReceptionShiftStatus::Open->value)->pluck('receptionist_id');
        $access = app(PropertyAccessService::class);

        return User::query()
            ->where('is_active', true)
            ->whereNotIn('id', $busyIds)
            ->with(['profile', 'roles', 'permissions'])
            ->orderBy('name')
            ->get()
            ->filter(fn (User $user): bool => $user->can(PropertyBookingPermission::ACCESS_POB)
                && $access->canAccess($user, $register->property_id))
            ->values();
    }

    /** Open the reception-shift assignment dialog. */
    public function openShiftDialog(): void
    {
        Gate::authorize('open', ReceptionShift::class);
        $this->closeDialog();
        $this->openForm->resetForOpen();
        $this->dialog = 'open';
    }

    /** Open a validated shift through the domain service. */
    public function openShift(ReceptionShiftService $shifts): void
    {
        Gate::authorize('open', ReceptionShift::class);
        $this->openForm->validate();
        $register = $this->availableRegisters->firstWhere('id', (int) $this->openForm->registerId);
        $receptionist = $this->availableReceptionists->firstWhere('id', (int) $this->openForm->receptionistId);
        abort_unless($register instanceof ReceptionRegister && $receptionist instanceof User, 422);

        try {
            $shifts->open(
                register: $register,
                receptionist: $receptionist,
                actor: $this->actor(),
                openingFloatMinor: $this->openForm->openingFloatMinor(),
                note: trim($this->openForm->note) ?: null,
            );
        } catch (ReceptionShiftException|InvalidArgumentException $exception) {
            $this->addError('management', $exception->getMessage());

            return;
        }

        $this->closeDialog();
        session()->flash('success', 'Reception shift opened.');
        $this->refreshComputed();
    }

    /** Open the cash reconciliation dialog for one scoped shift. */
    public function openCloseDialog(int $shiftId): void
    {
        $shift = $this->findShift($shiftId);
        Gate::authorize('close', $shift);
        abort_unless($shift->isOpen(), 422);
        $this->closeDialog();
        $this->selectedShiftId = $shift->getKey();
        $this->closeForm->fillFromShift($shift);
        $this->dialog = 'close';
    }

    /** Reconcile and close one shift through the domain service. */
    public function closeShift(ReceptionShiftService $shifts): void
    {
        $shift = $this->findShift((int) $this->selectedShiftId);
        Gate::authorize('close', $shift);
        $this->closeForm->validate();

        try {
            $closed = $shifts->close(
                shift: $shift,
                actor: $this->actor(),
                countedCashMinor: $this->closeForm->countedCashMinor(),
                note: trim($this->closeForm->note) ?: null,
            );
        } catch (ReceptionShiftException|InvalidArgumentException $exception) {
            $this->addError('management', $exception->getMessage());

            return;
        }

        $this->closeDialog();
        session()->flash('success', 'Reception shift closed with variance '.MoneyFormatter::format(abs((int) $closed->variance_minor), $closed->currency).'.');
        $this->refreshComputed();
    }

    /** Close modal state and clear both shift forms. */
    public function closeDialog(): void
    {
        $this->dialog = '';
        $this->selectedShiftId = null;
        $this->openForm->resetForOpen();
        $this->closeForm->resetForClose();
        $this->resetValidation();
    }

    /** Render the module-owned reception shift manager. */
    public function render(): View
    {
        return view('property-booking::livewire.pob.admin.reception-shift-manager');
    }

    /** Return a fresh property-scoped shift query. */
    private function scopedQuery(): Builder
    {
        return app(PropertyAccessService::class)->scope(
            ReceptionShift::query(),
            $this->actor(),
            'property_booking_shifts.property_id',
        );
    }

    /** Find one shift without permitting cross-property identifiers. */
    private function findShift(int $shiftId): ReceptionShift
    {
        return $this->scopedQuery()->with(['register', 'receptionist'])->findOrFail($shiftId);
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
        unset($this->shifts, $this->statistics, $this->availableRegisters, $this->availableReceptionists);
    }
}
