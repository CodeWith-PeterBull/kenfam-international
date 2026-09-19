<?php

/** Manager workspace that opens, monitors, closes, and signs off booking-desk shifts. */

declare(strict_types=1);

namespace App\Modules\TravelTours\PointOfBooking\Livewire\Admin;

use App\Models\User;
use App\Modules\TravelTours\PointOfBooking\Enums\ShiftStatus;
use App\Modules\TravelTours\PointOfBooking\Exceptions\PointOfBookingException;
use App\Modules\TravelTours\PointOfBooking\Livewire\Forms\ShiftCloseForm;
use App\Modules\TravelTours\PointOfBooking\Livewire\Forms\ShiftOpenForm;
use App\Modules\TravelTours\PointOfBooking\Models\BookingRegister;
use App\Modules\TravelTours\PointOfBooking\Models\BookingShift;
use App\Modules\TravelTours\PointOfBooking\Services\BookingShiftService;
use App\Modules\TravelTours\Support\LikePattern;
use App\Modules\TravelTours\Support\MoneyFormatter;
use App\Modules\TravelTours\Support\TravelToursPermission;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * A shift is opened on a free register for a free operator with a counted
 * float, and closed against the counted drawer; the ledger supplies the
 * expected figure and the service records the variance. Closed shifts with
 * a variance can be signed off once the difference has been explained.
 */
final class ShiftManager extends Component
{
    use WithPagination;

    private const PER_PAGE = 12;

    #[Url(as: 'shift-q', except: '')]
    public string $search = '';

    #[Url(as: 'shift-state', except: 'open')]
    public string $state = 'open';

    public string $dialog = '';

    #[Locked]
    public ?int $selectedShiftId = null;

    public ShiftOpenForm $openForm;

    public ShiftCloseForm $closeForm;

    public string $signOffNote = '';

    /** Require shift management on every request. */
    public function boot(): void
    {
        Gate::authorize('open', BookingShift::class);
    }

    /** Reset pagination after a search change. */
    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /** Normalise the state filter and reset pagination. */
    public function updatedState(): void
    {
        if (! in_array($this->state, ['open', 'closed', 'all'], true)) {
            $this->state = 'open';
        }
        $this->resetPage();
    }

    /** Refresh the operator options when a register is chosen. */
    public function updatedOpenFormRegisterId(): void
    {
        $this->openForm->operatorId = '';
        unset($this->availableOperators);
    }

    /** @return array{open: int, closed_today: int, expected_cash_minor: int, variance_minor: int} */
    #[Computed]
    public function statistics(): array
    {
        $ledger = app(BookingShiftService::class);
        $open = BookingShift::query()->open()->get();

        return [
            'open' => $open->count(),
            'closed_today' => BookingShift::query()->whereIn('status', [ShiftStatus::Closed->value, ShiftStatus::Reconciled->value])->whereDate('closed_at', today())->count(),
            'expected_cash_minor' => (int) $open->sum(fn (BookingShift $shift): int => $ledger->expectedCashMinor($shift)),
            'variance_minor' => (int) BookingShift::query()->whereDate('closed_at', today())->sum('variance_minor'),
        ];
    }

    /**
     * Shift history newest first with its figures.
     *
     * @return LengthAwarePaginator<int, BookingShift>
     */
    #[Computed]
    public function shifts(): LengthAwarePaginator
    {
        return BookingShift::query()
            ->with(['register', 'operator', 'reconciler'])
            ->withCount(['bookings', 'payments'])
            ->withSum('movements as ledger_cash_minor', 'amount_minor')
            ->when($this->state === 'open', fn (Builder $query): Builder => $query->open())
            ->when($this->state === 'closed', fn (Builder $query): Builder => $query->whereIn('status', [ShiftStatus::Closed->value, ShiftStatus::Reconciled->value]))
            ->when(trim($this->search) !== '', function (Builder $query): void {
                $like = LikePattern::contains($this->search);
                $query->where(fn (Builder $match): Builder => $match
                    ->whereHas('register', fn (Builder $register): Builder => $register->whereRaw('name '.LikePattern::CLAUSE, [$like])->orWhereRaw('code '.LikePattern::CLAUSE, [$like]))
                    ->orWhereHas('operator', fn (Builder $user): Builder => $user->whereRaw('name '.LikePattern::CLAUSE, [$like])->orWhereRaw('email '.LikePattern::CLAUSE, [$like])));
            })
            ->latest('opened_at')
            ->paginate(self::PER_PAGE);
    }

    /**
     * Active registers with no open shift.
     *
     * @return Collection<int, BookingRegister>
     */
    #[Computed]
    public function availableRegisters(): Collection
    {
        return BookingRegister::query()->active()->whereDoesntHave('shifts', fn (Builder $query): Builder => $query->open())->orderBy('name')->get();
    }

    /**
     * Active desk operators with no open shift, once a register is chosen.
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function availableOperators(): Collection
    {
        if (! $this->availableRegisters->contains('id', (int) $this->openForm->registerId)) {
            return new Collection;
        }
        $busy = BookingShift::query()->open()->pluck('operator_id');

        return User::query()
            ->where('is_active', true)
            ->whereNotIn('id', $busy)
            ->with(['roles', 'permissions'])
            ->orderBy('name')
            ->get()
            ->filter(fn (User $user): bool => $user->can(TravelToursPermission::ACCESS_POB))
            ->values();
    }

    /** Open the shift assignment dialog. */
    public function openShiftDialog(): void
    {
        Gate::authorize('open', BookingShift::class);
        $this->closeDialog();
        $this->openForm->resetForOpen();
        $this->dialog = 'open';
    }

    /** Open a validated shift through the service. */
    public function openShift(BookingShiftService $shifts): void
    {
        Gate::authorize('open', BookingShift::class);
        $this->openForm->validate();
        $register = $this->availableRegisters->firstWhere('id', (int) $this->openForm->registerId);
        $operator = $this->availableOperators->firstWhere('id', (int) $this->openForm->operatorId);
        if (! $register instanceof BookingRegister || ! $operator instanceof User) {
            $this->addError('management', 'Choose a free register and a free operator.');

            return;
        }

        try {
            $shifts->open($register, $operator, $this->actor(), $this->openForm->openingFloatMinor(), $this->openForm->note);
        } catch (PointOfBookingException|InvalidArgumentException $exception) {
            $this->addError('management', $exception->getMessage());

            return;
        }

        $this->closeDialog();
        session()->flash('success', 'Shift opened.');
        $this->refreshComputed();
    }

    /** Open the cash reconciliation dialog for one open shift. */
    public function openCloseDialog(int $shiftId, BookingShiftService $shifts): void
    {
        $shift = BookingShift::query()->with(['register', 'operator'])->findOrFail($shiftId);
        Gate::authorize('close', $shift);
        if ($shift->status !== ShiftStatus::Open) {
            $this->addError('management', 'This shift is already closed.');

            return;
        }
        $this->closeDialog();
        $this->selectedShiftId = $shift->getKey();
        $this->closeForm->fillFromShift($shift, $shifts->expectedCashMinor($shift));
        $this->dialog = 'close';
    }

    /** Close one shift against the counted cash through the service. */
    public function closeShift(BookingShiftService $shifts): void
    {
        $shift = BookingShift::query()->findOrFail((int) $this->selectedShiftId);
        Gate::authorize('close', $shift);
        $this->closeForm->validate();

        try {
            $closed = $shifts->close($shift, $this->actor(), $this->closeForm->countedCashMinor((int) $shift->currency_exponent), $this->closeForm->note);
        } catch (PointOfBookingException|InvalidArgumentException $exception) {
            $this->addError('management', $exception->getMessage());

            return;
        }

        $this->closeDialog();
        $variance = MoneyFormatter::format((int) $closed->variance_minor, $closed->currency, (int) $closed->currency_exponent);
        session()->flash('success', $closed->variance_minor === 0 ? 'Shift closed with no variance.' : "Shift closed with a variance of {$variance}.");
        $this->refreshComputed();
    }

    /** Open the sign-off dialog for a closed shift. */
    public function openSignOff(int $shiftId): void
    {
        $shift = BookingShift::query()->findOrFail($shiftId);
        Gate::authorize('update', $shift);
        $this->closeDialog();
        $this->selectedShiftId = $shift->getKey();
        $this->dialog = 'sign-off';
    }

    /** Sign off a closed shift once its variance has been reviewed. */
    public function signOff(BookingShiftService $shifts): void
    {
        $shift = BookingShift::query()->findOrFail((int) $this->selectedShiftId);
        Gate::authorize('update', $shift);
        $this->validate(['signOffNote' => ['nullable', 'string', 'max:2000']]);

        try {
            $shifts->reconcile($shift, $this->signOffNote, $this->actor()->getKey());
        } catch (PointOfBookingException $exception) {
            $this->addError('management', $exception->getMessage());

            return;
        }

        $this->closeDialog();
        session()->flash('success', 'Shift signed off.');
        $this->refreshComputed();
    }

    /** Close any dialog and clear the forms. */
    public function closeDialog(): void
    {
        $this->dialog = '';
        $this->selectedShiftId = null;
        $this->signOffNote = '';
        $this->openForm->resetForOpen();
        $this->closeForm->resetForClose();
        $this->resetValidation();
    }

    /** Render the shift manager with the shift a dialog is acting on. */
    public function render(): View
    {
        return view('travel-tours::livewire.pob.admin.shift-manager', [
            'selectedShift' => $this->selectedShiftId === null ? null : BookingShift::query()->with(['register', 'operator'])->find($this->selectedShiftId),
        ]);
    }

    /** Resolve the authenticated manager. */
    private function actor(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    /** Forget cached projections after a mutation. */
    private function refreshComputed(): void
    {
        unset($this->shifts, $this->statistics, $this->availableRegisters, $this->availableOperators);
    }
}
