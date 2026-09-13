<?php

declare(strict_types=1);

namespace App\Modules\Commerce\PointOfSale\Livewire\Admin;

use App\Models\User;
use App\Modules\Commerce\Exceptions\RegisterException;
use App\Modules\Commerce\PointOfSale\Enums\TillSessionStatus;
use App\Modules\Commerce\PointOfSale\Livewire\Forms\RegisterForm;
use App\Modules\Commerce\PointOfSale\Models\Register;
use App\Modules\Commerce\PointOfSale\Services\RegisterService;
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
 * Permission-gated register configuration workspace.
 */
final class RegisterManager extends Component
{
    use WithPagination;

    #[Url(as: 'register-q', except: '')]
    public string $search = '';

    #[Url(as: 'register-state', except: 'all')]
    public string $state = 'all';

    public string $dialog = '';

    #[Locked]
    public ?int $selectedRegisterId = null;

    public RegisterForm $form;

    public function boot(): void
    {
        Gate::authorize('viewAny', Register::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

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
            'total' => Register::query()->count(),
            'active' => Register::query()->where('is_active', true)->count(),
            'open' => Register::query()->whereHas('tillSessions', fn (Builder $query): Builder => $query->where('status', TillSessionStatus::Open->value))->count(),
        ];
    }

    #[Computed]
    public function registers(): LengthAwarePaginator
    {
        return Register::query()
            ->withCount(['tillSessions', 'orders'])
            ->with(['tillSessions' => fn ($query) => $query->where('status', TillSessionStatus::Open->value)->with('opener')])
            ->when($this->state === 'active', fn (Builder $query): Builder => $query->where('is_active', true))
            ->when($this->state === 'inactive', fn (Builder $query): Builder => $query->where('is_active', false))
            ->when(trim($this->search) !== '', function (Builder $query): void {
                $search = '%'.trim($this->search).'%';
                $query->where(fn (Builder $match): Builder => $match
                    ->where('name', 'like', $search)
                    ->orWhere('code', 'like', $search)
                    ->orWhere('location_label', 'like', $search));
            })
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(12);
    }

    public function openCreate(): void
    {
        Gate::authorize('create', Register::class);
        $this->closeDialog();
        $this->form->resetForCreate();
        $this->dialog = 'form';
    }

    public function openEdit(int $registerId): void
    {
        $register = $this->findRegister($registerId);
        Gate::authorize('update', $register);
        $this->closeDialog();
        $this->selectedRegisterId = $register->id;
        $this->form->fillFromRegister($register);
        $this->dialog = 'form';
    }

    public function save(RegisterService $registers): void
    {
        $this->form->validate();
        $actor = $this->actor();

        try {
            if ($this->selectedRegisterId === null) {
                Gate::authorize('create', Register::class);
                $registers->create($this->form->payload(), $actor);
                $message = 'Register created.';
            } else {
                $register = $this->findRegister($this->selectedRegisterId);
                Gate::authorize('update', $register);
                $registers->update($register, $this->form->payload(), $actor);
                $message = 'Register updated.';
            }
        } catch (RegisterException $exception) {
            $this->addError('management', $exception->getMessage());

            return;
        }

        $this->closeDialog();
        session()->flash('success', $message);
        unset($this->registers, $this->statistics);
    }

    public function toggleActive(int $registerId, RegisterService $registers): void
    {
        $register = $this->findRegister($registerId);
        Gate::authorize('update', $register);

        try {
            $updated = $registers->setActive($register, ! $register->is_active, $this->actor());
        } catch (RegisterException $exception) {
            $this->addError('management', $exception->getMessage());

            return;
        }

        session()->flash('success', $updated->is_active ? 'Register activated.' : 'Register deactivated.');
        unset($this->registers, $this->statistics);
    }

    public function closeDialog(): void
    {
        $this->dialog = '';
        $this->selectedRegisterId = null;
        $this->form->resetForCreate();
        $this->resetValidation();
    }

    public function render(): View
    {
        return view('commerce::livewire.pos.admin.register-manager');
    }

    private function findRegister(int $registerId): Register
    {
        return Register::query()->findOrFail($registerId);
    }

    private function actor(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
