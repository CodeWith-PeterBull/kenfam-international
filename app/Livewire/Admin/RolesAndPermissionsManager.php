<?php

namespace App\Livewire\Admin;

use App\Enums\UserType;
use App\Livewire\Forms\RoleForm;
use App\Services\RoleManagementService;
use App\Support\CmsPermission;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsManager extends Component
{
    public RoleForm $form;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    public string $dialog = '';

    #[Locked]
    public ?int $selectedRoleId = null;

    public function boot(): void
    {
        Gate::authorize(CmsPermission::VIEW_ROLES_AND_PERMISSIONS);
    }

    /** @return Collection<int, Role> */
    #[Computed]
    public function roles(): Collection
    {
        return Role::query()
            ->where('guard_name', 'web')
            ->with('permissions')
            ->withCount('users')
            ->when(trim($this->search) !== '', fn ($query) => $query->where('name', 'like', '%'.trim($this->search).'%'))
            ->orderBy('name')
            ->get();
    }

    /** @return array<string, array<string, array{label: string, group: string, description: string}>> */
    #[Computed]
    public function groupedPermissions(): array
    {
        $groups = [];

        foreach (CmsPermission::catalogue() as $name => $metadata) {
            $groups[$metadata['group']][$name] = $metadata;
        }

        return $groups;
    }

    /** @return array{roles: int, permissions: int, assignments: int, custom: int} */
    #[Computed]
    public function statistics(): array
    {
        $roles = Role::query()->where('guard_name', 'web');

        return [
            'roles' => (clone $roles)->count(),
            'permissions' => count(CmsPermission::foundational()),
            'assignments' => (int) (clone $roles)->withCount('users')->get()->sum('users_count'),
            'custom' => (clone $roles)->whereNotIn('name', UserType::values())->count(),
        ];
    }

    #[Computed]
    public function selectedRole(): ?Role
    {
        if ($this->selectedRoleId === null) {
            return null;
        }

        return Role::query()->where('guard_name', 'web')->with('permissions')->withCount('users')->find($this->selectedRoleId);
    }

    public function openCreate(): void
    {
        Gate::authorize(CmsPermission::MANAGE_ROLES_AND_PERMISSIONS);
        $this->closeDialog();
        $this->form->resetForCreate();
        $this->dialog = 'form';
    }

    public function openEdit(int $roleId): void
    {
        Gate::authorize(CmsPermission::MANAGE_ROLES_AND_PERMISSIONS);
        $role = $this->findRole($roleId);
        abort_if($role->name === UserType::SystemAdministrator->value, 403);
        $this->closeDialog();
        $this->selectedRoleId = $role->id;
        $this->form->fillFromRole($role);
        $this->dialog = 'form';
    }

    public function confirmDelete(int $roleId): void
    {
        Gate::authorize(CmsPermission::MANAGE_ROLES_AND_PERMISSIONS);
        $role = $this->findRole($roleId);
        abort_if(in_array($role->name, UserType::values(), true), 403);
        $this->closeDialog();
        $this->selectedRoleId = $role->id;
        $this->dialog = 'delete';
        unset($this->selectedRole);
    }

    public function togglePermissionGroup(string $group, bool $selected): void
    {
        Gate::authorize(CmsPermission::MANAGE_ROLES_AND_PERMISSIONS);
        $permissions = array_keys($this->groupedPermissions[$group] ?? []);
        abort_if($permissions === [], 404);

        $this->form->permissions = $selected
            ? array_values(array_unique([...$this->form->permissions, ...$permissions]))
            : array_values(array_diff($this->form->permissions, $permissions));
    }

    public function save(RoleManagementService $roles): void
    {
        Gate::authorize(CmsPermission::MANAGE_ROLES_AND_PERMISSIONS);
        $this->resetErrorBag('management');
        $this->form->validate();

        try {
            if ($this->selectedRoleId === null) {
                $roles->create($this->form->name, $this->form->permissions, auth()->user());
                $message = 'Access role created successfully.';
            } else {
                $role = $this->findRole($this->selectedRoleId);
                if (! $this->form->role?->is($role)) {
                    abort(404);
                }

                $roles->update($role, $this->form->name, $this->form->permissions, auth()->user());
                $message = 'Access role updated successfully.';
            }
        } catch (DomainException|InvalidArgumentException $exception) {
            $this->addError('management', $exception->getMessage());

            return;
        }

        $this->closeDialog();
        session()->flash('success', $message);
        $this->refreshRoles();
    }

    public function delete(RoleManagementService $roles): void
    {
        Gate::authorize(CmsPermission::MANAGE_ROLES_AND_PERMISSIONS);
        $this->resetErrorBag('management');
        $role = $this->findRole($this->selectedRoleId);

        try {
            $roles->delete($role, auth()->user());
        } catch (DomainException|InvalidArgumentException $exception) {
            $this->addError('management', $exception->getMessage());
            $this->dialog = '';

            return;
        }

        $this->closeDialog();
        session()->flash('success', 'Access role deleted successfully.');
        $this->refreshRoles();
    }

    public function closeDialog(): void
    {
        $this->dialog = '';
        $this->selectedRoleId = null;
        $this->form->resetForCreate();
        $this->resetValidation();
        unset($this->selectedRole);
    }

    public function render(): View
    {
        return view('livewire.admin.roles-and-permissions-manager');
    }

    private function findRole(?int $roleId): Role
    {
        abort_if($roleId === null, 404);

        return Role::query()->where('guard_name', 'web')->with('permissions')->withCount('users')->findOrFail($roleId);
    }

    private function refreshRoles(): void
    {
        unset($this->roles, $this->statistics, $this->selectedRole);
    }
}
