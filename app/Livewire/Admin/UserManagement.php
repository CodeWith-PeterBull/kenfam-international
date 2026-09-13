<?php

namespace App\Livewire\Admin;

use App\Enums\IdentificationType;
use App\Enums\UserType;
use App\Livewire\Forms\UserForm;
use App\Models\User;
use App\Services\UserManagementService;
use App\Support\CmsPermission;
use DomainException;
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
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

class UserManagement extends Component
{
    use WithFileUploads, WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'type', except: '')]
    public string $userTypeFilter = '';

    #[Url(as: 'status', except: '')]
    public string $statusFilter = '';

    #[Url(as: 'role', except: '')]
    public string $roleFilter = '';

    #[Url(as: 'per-page', except: 15)]
    public int $perPage = 15;

    public UserForm $form;

    public string $dialog = '';

    #[Locked]
    public ?int $selectedUserId = null;

    public $profilePhotoUpload = null;

    public function boot(): void
    {
        Gate::authorize(CmsPermission::VIEW_USERS);
    }

    public function updatedSearch(): void
    {
        $this->resetUserPage();
    }

    public function updatedUserTypeFilter(): void
    {
        $this->resetUserPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetUserPage();
    }

    public function updatedRoleFilter(): void
    {
        $this->resetUserPage();
    }

    public function updatedPerPage(): void
    {
        if (! in_array($this->perPage, [10, 15, 25, 50], true)) {
            $this->perPage = 15;
        }

        $this->resetUserPage();
    }

    public function updatedProfilePhotoUpload(): void
    {
        Gate::authorize(CmsPermission::MANAGE_USERS);
        $this->validateOnly('profilePhotoUpload', $this->photoRules());
    }

    /** @return list<UserType> */
    #[Computed]
    public function userTypes(): array
    {
        return UserType::cases();
    }

    /** @return list<IdentificationType> */
    #[Computed]
    public function identificationTypes(): array
    {
        return IdentificationType::cases();
    }

    /** @return Collection<int, Role> */
    #[Computed]
    public function roles(): Collection
    {
        return Role::query()->where('guard_name', 'web')->orderBy('name')->get();
    }

    /** @return Collection<int, Role> */
    #[Computed]
    public function customRoles(): Collection
    {
        return $this->roles->reject(
            static fn (Role $role): bool => in_array($role->name, UserType::values(), true),
        )->values();
    }

    /** @return array{total: int, active: int, administrators: int, incomplete: int} */
    #[Computed]
    public function statistics(): array
    {
        return [
            'total' => User::query()->count(),
            'active' => User::query()->where('is_active', true)->count(),
            'administrators' => User::query()->where('user_type', UserType::SystemAdministrator->value)->count(),
            'incomplete' => User::query()->whereDoesntHave('profile')->count(),
        ];
    }

    #[Computed]
    public function users(): LengthAwarePaginator
    {
        return $this->filteredQuery()
            ->with(['profile', 'roles', 'media'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(in_array($this->perPage, [10, 15, 25, 50], true) ? $this->perPage : 15);
    }

    #[Computed]
    public function selectedUser(): ?User
    {
        if ($this->selectedUserId === null) {
            return null;
        }

        return User::query()->with(['profile', 'roles', 'media'])->find($this->selectedUserId);
    }

    public function openCreate(): void
    {
        Gate::authorize(CmsPermission::MANAGE_USERS);
        $this->closeDialog();
        $this->form->resetForCreate();
        $this->dialog = 'form';
    }

    public function openEdit(int $userId): void
    {
        Gate::authorize(CmsPermission::MANAGE_USERS);
        $user = $this->findUser($userId);
        $this->closeDialog();
        $this->selectedUserId = $user->id;
        $this->form->fillFromUser($user);
        $this->dialog = 'form';
    }

    public function openView(int $userId): void
    {
        $user = $this->findUser($userId);
        $this->closeDialog();
        $this->selectedUserId = $user->id;
        $this->dialog = 'view';
        unset($this->selectedUser);
    }

    public function confirmDelete(int $userId): void
    {
        Gate::authorize(CmsPermission::MANAGE_USERS);
        $user = $this->findUser($userId);
        $this->closeDialog();
        $this->selectedUserId = $user->id;
        $this->dialog = 'delete';
        unset($this->selectedUser);
    }

    public function save(UserManagementService $users): void
    {
        Gate::authorize(CmsPermission::MANAGE_USERS);
        $this->resetErrorBag('management');
        $this->form->validate();
        $this->validate($this->photoRules());

        try {
            if ($this->selectedUserId === null) {
                $users->create(
                    accountAttributes: $this->form->accountPayload(),
                    profileAttributes: $this->form->profilePayload(),
                    additionalRoles: $this->form->additionalRoles,
                    profilePhoto: $this->profilePhotoUpload,
                    actor: auth()->user(),
                );
                $message = 'User account created successfully.';
            } else {
                $user = $this->findUser($this->selectedUserId);
                if (! $this->form->user?->is($user)) {
                    abort(404);
                }

                $users->update(
                    user: $user,
                    accountAttributes: $this->form->accountPayload(),
                    profileAttributes: $this->form->profilePayload(),
                    additionalRoles: $this->form->additionalRoles,
                    profilePhoto: $this->profilePhotoUpload,
                    removeProfilePhoto: $this->form->removeProfilePhoto,
                    actor: auth()->user(),
                );
                $message = 'User account updated successfully.';
            }
        } catch (DomainException|InvalidArgumentException $exception) {
            $this->addError('management', $exception->getMessage());

            return;
        }

        $this->closeDialog();
        session()->flash('success', $message);
        $this->refreshUsers();
    }

    public function toggleStatus(int $userId, UserManagementService $users): void
    {
        Gate::authorize(CmsPermission::MANAGE_USERS);
        $this->resetErrorBag('management');
        $user = $this->findUser($userId);
        $activate = ! $user->is_active;

        try {
            $users->setActive($user, $activate, auth()->user());
        } catch (DomainException $exception) {
            $this->addError('management', $exception->getMessage());

            return;
        }

        session()->flash('success', $activate ? 'User account activated.' : 'User account deactivated.');
        $this->refreshUsers();
    }

    public function delete(UserManagementService $users): void
    {
        Gate::authorize(CmsPermission::MANAGE_USERS);
        $this->resetErrorBag('management');
        $user = $this->findUser($this->selectedUserId);

        try {
            $users->deleteManagedUser($user, auth()->user());
        } catch (DomainException $exception) {
            $this->addError('management', $exception->getMessage());
            $this->dialog = '';

            return;
        }

        $this->closeDialog();
        session()->flash('success', 'User account deleted successfully.');
        $this->refreshUsers();
    }

    public function closeDialog(): void
    {
        $this->dialog = '';
        $this->selectedUserId = null;
        $this->profilePhotoUpload = null;
        $this->form->resetForCreate();
        $this->resetValidation();
        unset($this->selectedUser);
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->userTypeFilter = '';
        $this->statusFilter = '';
        $this->roleFilter = '';
        $this->resetUserPage();
    }

    public function render(): View
    {
        return view('livewire.admin.user-management');
    }

    /** @return array<string, list<string>> */
    private function photoRules(): array
    {
        return [
            'profilePhotoUpload' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
        ];
    }

    private function filteredQuery(): Builder
    {
        $search = trim($this->search);

        return User::query()
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $match) use ($search): void {
                    $match->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhereHas('profile', function (Builder $profile) use ($search): void {
                            $profile->where('first_name', 'like', "%{$search}%")
                                ->orWhere('middle_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%")
                                ->orWhere('identification_number', 'like', "%{$search}%");
                        });
                });
            })
            ->when(UserType::tryFrom($this->userTypeFilter), fn (Builder $query, UserType $type) => $query->where('user_type', $type->value))
            ->when(in_array($this->statusFilter, ['active', 'inactive'], true), fn (Builder $query) => $query->where('is_active', $this->statusFilter === 'active'))
            ->when($this->roles->contains('name', $this->roleFilter), fn (Builder $query) => $query->role($this->roleFilter));
    }

    private function findUser(?int $userId): User
    {
        abort_if($userId === null, 404);

        return User::query()->with(['profile', 'roles', 'media'])->findOrFail($userId);
    }

    private function resetUserPage(): void
    {
        $this->resetPage();
        $this->refreshUsers();
    }

    private function refreshUsers(): void
    {
        unset($this->users, $this->statistics, $this->roles, $this->customRoles, $this->selectedUser);
    }
}
