<?php

declare(strict_types=1);

namespace App\Modules\Commerce\PointOfSale\Livewire\Admin;

use App\Models\User;
use App\Modules\Commerce\Exceptions\TillSessionException;
use App\Modules\Commerce\PointOfSale\Enums\TillSessionStatus;
use App\Modules\Commerce\PointOfSale\Livewire\Forms\TillCloseForm;
use App\Modules\Commerce\PointOfSale\Livewire\Forms\TillOpenForm;
use App\Modules\Commerce\PointOfSale\Models\Register;
use App\Modules\Commerce\PointOfSale\Models\TillSession;
use App\Modules\Commerce\PointOfSale\Services\TillService;
use App\Modules\Commerce\Support\CommercePermission;
use App\Modules\Commerce\Support\MoneyFormatter;
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

/**
 * Opens, monitors, reconciles, and closes cashier till sessions.
 */
final class TillManager extends Component
{
    use WithPagination;

    #[Url(as: 'till-q', except: '')]
    public string $search = '';

    #[Url(as: 'till-state', except: 'open')]
    public string $state = 'open';

    public string $dialog = '';

    #[Locked]
    public ?int $selectedTillId = null;

    public TillOpenForm $openForm;

    public TillCloseForm $closeForm;

    public function boot(): void
    {
        Gate::authorize('viewAny', TillSession::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedState(): void
    {
        if (! in_array($this->state, ['open', 'closed', 'all'], true)) {
            $this->state = 'open';
        }
        $this->resetPage();
    }

    /** @return array{open: int, closed_today: int, expected_cash_minor: int, variance_minor: int} */
    #[Computed]
    public function statistics(): array
    {
        return [
            'open' => TillSession::query()->where('status', TillSessionStatus::Open->value)->count(),
            'closed_today' => TillSession::query()->where('status', TillSessionStatus::Closed->value)->whereDate('closed_at', today())->count(),
            'expected_cash_minor' => (int) TillSession::query()->where('status', TillSessionStatus::Open->value)->sum('expected_cash_minor'),
            'variance_minor' => (int) TillSession::query()->whereDate('closed_at', today())->sum('variance_minor'),
        ];
    }

    #[Computed]
    public function sessions(): LengthAwarePaginator
    {
        return TillSession::query()
            ->with(['register', 'opener.profile', 'closer.profile'])
            ->withCount(['orders', 'payments'])
            ->when($this->state !== 'all', fn (Builder $query): Builder => $query->where('status', $this->state))
            ->when(trim($this->search) !== '', function (Builder $query): void {
                $search = '%'.trim($this->search).'%';
                $query->where(fn (Builder $match): Builder => $match
                    ->whereHas('register', fn (Builder $register): Builder => $register->where('name', 'like', $search)->orWhere('code', 'like', $search))
                    ->orWhereHas('opener', fn (Builder $user): Builder => $user->where('name', 'like', $search)->orWhere('email', 'like', $search)));
            })
            ->latest('opened_at')
            ->paginate(12);
    }

    /** @return Collection<int, Register> */
    #[Computed]
    public function availableRegisters(): Collection
    {
        return Register::query()
            ->where('is_active', true)
            ->whereDoesntHave('tillSessions', fn (Builder $query): Builder => $query->where('status', TillSessionStatus::Open->value))
            ->orderBy('name')
            ->get();
    }

    /** @return Collection<int, User> */
    #[Computed]
    public function availableCashiers(): Collection
    {
        $busyIds = TillSession::query()->where('status', TillSessionStatus::Open->value)->pluck('opened_by');

        return User::query()
            ->where('is_active', true)
            ->whereNotIn('id', $busyIds)
            ->with(['profile', 'roles', 'permissions'])
            ->orderBy('name')
            ->get()
            ->filter(fn (User $user): bool => $user->can(CommercePermission::ACCESS_POS))
            ->values();
    }

    public function openTillDialog(): void
    {
        Gate::authorize('open', TillSession::class);
        $this->closeDialog();
        $this->openForm->resetForOpen();
        $this->dialog = 'open';
    }

    public function openTill(TillService $tills): void
    {
        Gate::authorize('open', TillSession::class);
        $this->openForm->validate();
        $register = Register::query()->findOrFail((int) $this->openForm->registerId);
        $cashier = User::query()->findOrFail((int) $this->openForm->cashierId);
        abort_unless($cashier->can(CommercePermission::ACCESS_POS), 422);

        try {
            $tills->open(
                register: $register,
                cashier: $cashier,
                openingFloatMinor: $this->openForm->openingFloatMinor(),
                note: trim($this->openForm->note) ?: null,
            );
        } catch (TillSessionException|InvalidArgumentException $exception) {
            $this->addError('management', $exception->getMessage());

            return;
        }

        $this->closeDialog();
        session()->flash('success', 'Till session opened.');
        $this->refresh();
    }

    public function openCloseDialog(int $tillId): void
    {
        $session = $this->findTill($tillId);
        Gate::authorize('close', $session);
        abort_unless($session->isOpen(), 422);
        $this->closeDialog();
        $this->selectedTillId = $session->id;
        $this->closeForm->fillFromTill($session);
        $this->dialog = 'close';
    }

    public function closeTill(TillService $tills): void
    {
        $session = $this->findTill((int) $this->selectedTillId);
        Gate::authorize('close', $session);
        $this->closeForm->validate();

        try {
            $closed = $tills->close(
                session: $session,
                actor: $this->actor(),
                countedCashMinor: $this->closeForm->countedCashMinor(),
                note: trim($this->closeForm->note) ?: null,
            );
        } catch (TillSessionException|InvalidArgumentException $exception) {
            $this->addError('management', $exception->getMessage());

            return;
        }

        $this->closeDialog();
        session()->flash('success', 'Till closed with variance '.MoneyFormatter::format(abs((int) $closed->variance_minor)).'.');
        $this->refresh();
    }

    public function closeDialog(): void
    {
        $this->dialog = '';
        $this->selectedTillId = null;
        $this->openForm->resetForOpen();
        $this->closeForm->resetForClose();
        $this->resetValidation();
    }

    public function render(): View
    {
        return view('commerce::livewire.pos.admin.till-manager');
    }

    private function findTill(int $tillId): TillSession
    {
        return TillSession::query()->with(['register', 'opener'])->findOrFail($tillId);
    }

    private function actor(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function refresh(): void
    {
        unset($this->sessions, $this->statistics, $this->availableRegisters, $this->availableCashiers);
    }
}
