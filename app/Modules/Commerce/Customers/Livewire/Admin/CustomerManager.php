<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Customers\Livewire\Admin;

use App\Models\User;
use App\Modules\Commerce\Customers\Livewire\Forms\CustomerForm;
use App\Modules\Commerce\Customers\Models\Customer;
use App\Modules\Commerce\Customers\Services\CustomerService;
use App\Modules\Commerce\Exceptions\CustomerException;
use App\Modules\Commerce\Orders\Models\Order;
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

/**
 * Authorized reusable-customer directory with reversible archival.
 */
final class CustomerManager extends Component
{
    use WithPagination;

    #[Url(as: 'customer-q', except: '')]
    public string $search = '';

    #[Url(as: 'customer-state', except: 'active')]
    public string $state = 'active';

    #[Url(as: 'customer-per-page', except: 15)]
    public int $perPage = 15;

    public CustomerForm $form;

    public string $dialog = '';

    #[Locked]
    public ?int $selectedCustomerId = null;

    public function boot(): void
    {
        Gate::authorize('viewAny', Customer::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage(pageName: 'customerPage');
    }

    public function updatedState(): void
    {
        $this->state = in_array($this->state, ['active', 'archived', 'all'], true) ? $this->state : 'active';
        $this->resetPage(pageName: 'customerPage');
    }

    public function updatedPerPage(): void
    {
        $this->perPage = in_array($this->perPage, [10, 15, 25, 50], true) ? $this->perPage : 15;
        $this->resetPage(pageName: 'customerPage');
    }

    /** @return array{active: int, archived: int, linked: int, orders: int} */
    #[Computed]
    public function statistics(): array
    {
        return [
            'active' => Customer::query()->count(),
            'archived' => Customer::onlyTrashed()->count(),
            'linked' => Customer::query()->whereNotNull('user_id')->count(),
            'orders' => Order::query()->whereNotNull('customer_id')->count(),
        ];
    }

    #[Computed]
    public function customers(): LengthAwarePaginator
    {
        $query = match ($this->state) {
            'archived' => Customer::onlyTrashed(),
            'all' => Customer::withTrashed(),
            default => Customer::query(),
        };
        $search = trim($this->search);

        return $query
            ->when($search !== '', function (Builder $customers) use ($search): void {
                $customers->where(function (Builder $matches) use ($search): void {
                    $matches
                        ->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('company', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->with('user')
            ->withCount('orders')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate($this->perPage, ['*'], 'customerPage');
    }

    /** @return Collection<int, User> */
    #[Computed]
    public function availableAccounts(): Collection
    {
        $linked = Customer::withTrashed()
            ->whereNotNull('user_id')
            ->when($this->selectedCustomerId !== null, fn (Builder $query): Builder => $query->whereKeyNot($this->selectedCustomerId))
            ->pluck('user_id');

        return User::query()->whereNotIn('id', $linked)->orderBy('name')->get(['id', 'name', 'email']);
    }

    public function openCreate(): void
    {
        Gate::authorize('create', Customer::class);
        $this->closeDialog();
        $this->form->resetForCreate();
        $this->dialog = 'form';
    }

    public function openEdit(int $customerId): void
    {
        $customer = $this->findCustomer($customerId);
        Gate::authorize('update', $customer);
        abort_if($customer->trashed(), 422);
        $this->closeDialog();
        $this->selectedCustomerId = $customer->id;
        $this->form->fillFromCustomer($customer);
        $this->dialog = 'form';
    }

    public function save(CustomerService $customers): void
    {
        $this->resetErrorBag('management');
        $this->form->validate();

        try {
            if ($this->selectedCustomerId === null) {
                Gate::authorize('create', Customer::class);
                $customers->create($this->form->payload(), $this->actor());
                $message = 'Customer created.';
            } else {
                $customer = $this->findCustomer($this->selectedCustomerId);
                Gate::authorize('update', $customer);
                $customers->update($customer, $this->form->payload(), $this->actor());
                $message = 'Customer updated.';
            }
        } catch (CustomerException $exception) {
            $this->addError('management', $exception->getMessage());

            return;
        }

        $this->closeDialog();
        session()->flash('success', $message);
        $this->refreshCustomers();
    }

    public function archive(int $customerId, CustomerService $customers): void
    {
        $customer = $this->findCustomer($customerId);
        Gate::authorize('delete', $customer);
        abort_if($customer->trashed(), 422);
        $customers->delete($customer, $this->actor());
        session()->flash('success', 'Customer archived. Historical orders were retained.');
        $this->refreshCustomers();
    }

    public function restore(int $customerId, CustomerService $customers): void
    {
        $customer = $this->findCustomer($customerId);
        Gate::authorize('update', $customer);
        $customers->restore($customer, $this->actor());
        session()->flash('success', 'Customer restored.');
        $this->refreshCustomers();
    }

    public function closeDialog(): void
    {
        $this->dialog = '';
        $this->selectedCustomerId = null;
        $this->form->resetForCreate();
        $this->resetValidation();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->state = 'active';
        $this->resetPage(pageName: 'customerPage');
    }

    public function render(): View
    {
        return view('commerce::livewire.admin.customer-manager');
    }

    private function findCustomer(int $customerId): Customer
    {
        return Customer::withTrashed()->findOrFail($customerId);
    }

    private function actor(): User
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 401);

        return $actor;
    }

    private function refreshCustomers(): void
    {
        unset($this->customers, $this->statistics, $this->availableAccounts);
    }
}
