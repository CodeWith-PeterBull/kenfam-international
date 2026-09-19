<?php

/** Manager workspace for the registers booking-desk shifts run on. */

declare(strict_types=1);

namespace App\Modules\TravelTours\PointOfBooking\Livewire\Admin;

use App\Models\User;
use App\Modules\TravelTours\PointOfBooking\Exceptions\PointOfBookingException;
use App\Modules\TravelTours\PointOfBooking\Livewire\Forms\RegisterForm;
use App\Modules\TravelTours\PointOfBooking\Models\BookingRegister;
use App\Modules\TravelTours\PointOfBooking\Services\BookingRegisterService;
use App\Modules\TravelTours\Support\LikePattern;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Registers are created and switched off here, never deleted, so every
 * shift and receipt keeps the desk it was taken on. Mutations go through
 * BookingRegisterService; this component only validates and authorises.
 */
final class RegisterManager extends Component
{
    use WithPagination;

    private const PER_PAGE = 12;

    #[Url(as: 'register-q', except: '')]
    public string $search = '';

    #[Url(as: 'register-state', except: 'all')]
    public string $state = 'all';

    public string $dialog = '';

    #[Locked]
    public ?int $selectedRegisterId = null;

    public RegisterForm $form;

    /** Require register management on every request. */
    public function boot(): void
    {
        Gate::authorize('create', BookingRegister::class);
    }

    /** Reset pagination after a search change. */
    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /** Normalise the active-state filter and reset pagination. */
    public function updatedState(): void
    {
        if (! in_array($this->state, ['all', 'active', 'inactive'], true)) {
            $this->state = 'all';
        }
        $this->resetPage();
    }

    /** @return array{total: int, active: int, open: int} */
    #[Computed]
    public function statistics(): array
    {
        return [
            'total' => BookingRegister::query()->count(),
            'active' => BookingRegister::query()->active()->count(),
            'open' => BookingRegister::query()->whereHas('shifts', fn (Builder $shift): Builder => $shift->open())->count(),
        ];
    }

    /**
     * The paginated register directory with each register's open shift.
     *
     * @return LengthAwarePaginator<int, BookingRegister>
     */
    #[Computed]
    public function registers(): LengthAwarePaginator
    {
        return BookingRegister::query()
            ->with(['shifts' => fn ($query) => $query->open()->with('operator')])
            ->withCount(['shifts', 'bookings'])
            ->when($this->state === 'active', fn (Builder $query): Builder => $query->where('is_active', true))
            ->when($this->state === 'inactive', fn (Builder $query): Builder => $query->where('is_active', false))
            ->when(trim($this->search) !== '', function (Builder $query): void {
                $like = LikePattern::contains($this->search);
                $query->where(fn (Builder $match): Builder => $match
                    ->whereRaw('name '.LikePattern::CLAUSE, [$like])
                    ->orWhereRaw('code '.LikePattern::CLAUSE, [$like])
                    ->orWhereRaw('location '.LikePattern::CLAUSE, [$like]));
            })
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(self::PER_PAGE);
    }

    /** Open an empty register form. */
    public function openCreate(): void
    {
        Gate::authorize('create', BookingRegister::class);
        $this->closeDialog();
        $this->form->resetForCreate();
        $this->dialog = 'form';
    }

    /** Open the form for an existing register. */
    public function openEdit(int $registerId): void
    {
        $register = BookingRegister::query()->findOrFail($registerId);
        Gate::authorize('update', $register);
        $this->closeDialog();
        $this->selectedRegisterId = $register->getKey();
        $this->form->fillFromRegister($register);
        $this->dialog = 'form';
    }

    /** Persist the validated register through its service. */
    public function save(BookingRegisterService $registers): void
    {
        $this->form->validate();
        $actor = $this->actor();

        try {
            if ($this->selectedRegisterId === null) {
                Gate::authorize('create', BookingRegister::class);
                $registers->create($this->form->payload(), $actor);
                $message = 'Register created.';
            } else {
                $register = BookingRegister::query()->findOrFail($this->selectedRegisterId);
                Gate::authorize('update', $register);
                $registers->update($register, $this->form->payload(), $actor);
                $message = 'Register updated.';
            }
        } catch (PointOfBookingException $exception) {
            $this->addError('management', $exception->getMessage());

            return;
        }

        $this->closeDialog();
        session()->flash('success', $message);
        $this->refreshComputed();
    }

    /** Switch a register on or off without touching its history. */
    public function toggleActive(int $registerId, BookingRegisterService $registers): void
    {
        $register = BookingRegister::query()->findOrFail($registerId);
        Gate::authorize('update', $register);

        try {
            $updated = $registers->setActive($register, ! $register->is_active, $this->actor());
        } catch (PointOfBookingException $exception) {
            $this->addError('management', $exception->getMessage());

            return;
        }

        session()->flash('success', $updated->is_active ? 'Register activated.' : 'Register deactivated.');
        $this->refreshComputed();
    }

    /** Close the dialog and clear transient form state. */
    public function closeDialog(): void
    {
        $this->dialog = '';
        $this->selectedRegisterId = null;
        $this->form->resetForCreate();
        $this->resetValidation();
    }

    /** Render the register manager. */
    public function render(): View
    {
        return view('travel-tours::livewire.pob.admin.register-manager');
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
        unset($this->registers, $this->statistics);
    }
}
