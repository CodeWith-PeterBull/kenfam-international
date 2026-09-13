<?php

namespace App\Livewire\Forms;

use App\Support\CmsPermission;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Validate;
use Livewire\Form;
use Spatie\Permission\Models\Role;

class RoleForm extends Form
{
    public ?Role $role = null;

    #[Validate]
    public string $name = '';

    /** @var list<string> */
    #[Validate]
    public array $permissions = [];

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:125',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique(config('permission.table_names.roles', 'roles'), 'name')
                    ->where('guard_name', 'web')
                    ->ignore($this->role),
            ],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'distinct', Rule::in(CmsPermission::foundational())],
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return ['permissions.*' => 'selected permission'];
    }

    public function fillFromRole(Role $role): void
    {
        $role->loadMissing('permissions');
        $this->role = $role;
        $this->name = $role->name;
        $this->permissions = $role->permissions->pluck('name')->sort()->values()->all();
        $this->resetValidation();
    }

    public function resetForCreate(): void
    {
        $this->reset();
        $this->role = null;
        $this->permissions = [];
        $this->resetValidation();
    }
}
