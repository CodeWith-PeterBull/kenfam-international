<?php

namespace App\Services;

use App\Contracts\RecordsSystemActivity;
use App\Enums\SystemActivitySeverity;
use App\Enums\UserType;
use App\Models\User;
use App\Support\CmsPermission;
use DomainException;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

final readonly class RoleManagementService
{
    public function __construct(
        private DatabaseManager $database,
        private RecordsSystemActivity $activities,
    ) {}

    /** @param list<string> $permissionNames */
    public function create(string $name, array $permissionNames, User $actor): Role
    {
        return $this->database->transaction(function () use ($name, $permissionNames, $actor): Role {
            $name = $this->normalizeName($name);
            $permissions = $this->resolvePermissions($permissionNames);
            $role = Role::query()->create(['name' => $name, 'guard_name' => 'web']);
            $role->syncPermissions($permissions);

            $this->activities->record(
                activityType: 'role.created',
                description: "Access role created: {$role->name}",
                actor: $actor,
                subject: $role,
                properties: ['permissions' => $permissions->pluck('name')->all()],
                severity: SystemActivitySeverity::Notice,
                source: 'roles-and-permissions',
            );

            return $role->loadCount('users')->load('permissions');
        });
    }

    /** @param list<string> $permissionNames */
    public function update(Role $role, string $name, array $permissionNames, User $actor): Role
    {
        return $this->database->transaction(function () use ($role, $name, $permissionNames, $actor): Role {
            $this->assertWebRole($role);
            if ($role->name === UserType::SystemAdministrator->value) {
                throw new DomainException('The system administrator role is managed by the permission seeder.');
            }

            $name = $this->normalizeName($name);
            if ($this->isBaseRole($role) && $name !== $role->name) {
                throw new DomainException('Base role identifiers cannot be renamed.');
            }

            $permissions = $this->resolvePermissions($permissionNames);
            $oldName = $role->name;
            $oldPermissions = $role->permissions()->pluck('name')->all();

            if (! $this->isBaseRole($role)) {
                $role->forceFill(['name' => $name])->save();
            }
            $role->syncPermissions($permissions);

            $this->activities->record(
                activityType: 'role.updated',
                description: "Access role updated: {$role->name}",
                actor: $actor,
                subject: $role,
                properties: [
                    'previous_name' => $oldName,
                    'previous_permissions' => $oldPermissions,
                    'permissions' => $permissions->pluck('name')->all(),
                ],
                severity: SystemActivitySeverity::Notice,
                source: 'roles-and-permissions',
            );

            return $role->refresh()->loadCount('users')->load('permissions');
        });
    }

    public function delete(Role $role, User $actor): void
    {
        $this->database->transaction(function () use ($role, $actor): void {
            $this->assertWebRole($role);
            if ($this->isBaseRole($role)) {
                throw new DomainException('Base roles cannot be deleted.');
            }

            $memberCount = $role->users()->count();
            if ($memberCount > 0) {
                throw new DomainException('Reassign all users before deleting this role.');
            }

            $name = $role->name;
            $this->activities->record(
                activityType: 'role.deleted',
                description: "Access role deleted: {$name}",
                actor: $actor,
                subject: $role,
                properties: ['permissions' => $role->permissions()->pluck('name')->all()],
                severity: SystemActivitySeverity::Warning,
                source: 'roles-and-permissions',
            );

            $role->delete();
        });
    }

    public function isBaseRole(Role $role): bool
    {
        return in_array($role->name, UserType::values(), true);
    }

    private function normalizeName(string $name): string
    {
        $name = Str::lower(trim($name));

        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $name) !== 1) {
            throw new InvalidArgumentException('Role names must use lowercase letters, numbers, and single hyphens.');
        }

        return $name;
    }

    /**
     * @param  list<string>  $permissionNames
     * @return Collection<int, Permission>
     */
    private function resolvePermissions(array $permissionNames)
    {
        $permissionNames = array_values(array_unique(array_map(
            static fn (mixed $permission): string => trim((string) $permission),
            $permissionNames,
        )));

        if (array_diff($permissionNames, CmsPermission::foundational()) !== []) {
            throw new InvalidArgumentException('Only code-owned CMS permissions may be assigned.');
        }

        $permissions = Permission::query()
            ->where('guard_name', 'web')
            ->whereIn('name', $permissionNames)
            ->get();

        if ($permissions->count() !== count($permissionNames)) {
            throw new InvalidArgumentException('One or more selected permissions are unavailable.');
        }

        return $permissions;
    }

    private function assertWebRole(Role $role): void
    {
        if ($role->guard_name !== 'web') {
            throw new InvalidArgumentException('Only web guard roles can be managed here.');
        }
    }
}
