<?php

namespace Maxiviper117\Access\Data;

use BackedEnum;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Maxiviper117\Access\Models\Assignment;
use Maxiviper117\Access\Models\Permission;
use Maxiviper117\Access\Models\Role;
use Maxiviper117\Access\Support\AccessCache;
use Maxiviper117\Access\Support\AccessChecker;
use Maxiviper117\Access\Support\PermissionNormalizer;

class AccessContext
{
    public function __construct(
        private readonly Model $actor,
        private ?Model $scope = null,
    ) {}

    public function in(Model $scope): self
    {
        $clone = clone $this;
        $clone->scope = $scope;

        return $clone;
    }

    public function createRole(BackedEnum|string $name, ?string $label = null, ?string $description = null): Role
    {
        $roleName = $name instanceof BackedEnum ? $name->value : $name;

        return Role::query()->create([
            'name' => $roleName,
            'label' => $label ?? str((string) $roleName)->headline(),
            'description' => $description,
            'is_global' => ! $this->scope instanceof Model,
            'is_system' => false,
            'scope_type' => $this->scope?->getMorphClass(),
            'scope_id' => $this->scope?->getKey(),
        ]);
    }

    public function deleteRole(BackedEnum|string|Role $role): bool
    {
        $roleModel = $this->findRoleInstance($role);

        if (! $roleModel instanceof Role) {
            return false;
        }

        if ($roleModel->is_system) {
            return false;
        }

        $roleModel->delete();
        app(AccessCache::class)->forget($this->actor, $this->scope);

        return true;
    }

    /** @param array<BackedEnum|string> $permissions */
    public function syncRolePermissions(BackedEnum|string|Role $role, array $permissions): self
    {
        $roleModel = $this->findRoleInstance($role);

        if (! $roleModel instanceof Role) {
            throw new \InvalidArgumentException('Role not found.');
        }

        if ($roleModel->is_system) {
            throw new \InvalidArgumentException('Cannot modify system roles.');
        }

        $ids = collect($permissions)
            ->map(fn (BackedEnum|string $permission): string => app(PermissionNormalizer::class)->normalize($permission))
            ->map(fn (string $name): int => (is_scalar(Permission::query()->firstOrCreate(['name' => $name])->getKey()) ? (int) Permission::query()->firstOrCreate(['name' => $name])->getKey() : 0))
            ->all();

        $roleModel->permissions()->sync($ids);
        app(AccessCache::class)->forget($this->actor, $this->scope);

        return $this;
    }

    public function addPermissionToRole(BackedEnum|string|Role $role, BackedEnum|string $permission): self
    {
        $roleModel = $this->findRoleInstance($role);

        if (! $roleModel instanceof Role) {
            throw new \InvalidArgumentException('Role not found.');
        }

        if ($roleModel->is_system) {
            throw new \InvalidArgumentException('Cannot modify system roles.');
        }

        $normalized = app(PermissionNormalizer::class)->normalize($permission);
        $permissionModel = Permission::query()->firstOrCreate(['name' => $normalized]);

        $roleModel->permissions()->syncWithoutDetaching([$permissionModel->getKey()]);
        app(AccessCache::class)->forget($this->actor, $this->scope);

        return $this;
    }

    public function removePermissionFromRole(BackedEnum|string|Role $role, BackedEnum|string $permission): self
    {
        $roleModel = $this->findRoleInstance($role);

        if (! $roleModel instanceof Role) {
            throw new \InvalidArgumentException('Role not found.');
        }

        if ($roleModel->is_system) {
            throw new \InvalidArgumentException('Cannot modify system roles.');
        }

        $normalized = app(PermissionNormalizer::class)->normalize($permission);
        $permissionModel = Permission::query()->where('name', $normalized)->first();

        if ($permissionModel) {
            $roleModel->permissions()->detach($permissionModel->getKey());
            app(AccessCache::class)->forget($this->actor, $this->scope);
        }

        return $this;
    }

    /** @return Collection<int, Role> */
    public function roles(): Collection
    {
        $query = Role::query();

        if ($this->scope instanceof Model) {
            $scope = $this->scope;
            $query->where(function ($q) use ($scope): void {
                $q->where(fn ($sub) => $sub->where('scope_type', $scope->getMorphClass())->where('scope_id', $scope->getKey()))
                    ->orWhere(fn ($sub) => $sub->whereNull('scope_type')->whereNull('scope_id'));
            });
        } else {
            $query->whereNull('scope_type')->whereNull('scope_id');
        }

        return $query->get();
    }

    private function findRoleInstance(BackedEnum|string|Role $role): ?Role
    {
        if ($role instanceof Role) {
            return $role;
        }

        $roleName = $role instanceof BackedEnum ? $role->value : $role;

        if ($this->scope instanceof Model) {
            $scopedRole = Role::query()
                ->where('name', $roleName)
                ->where('scope_type', $this->scope->getMorphClass())
                ->where('scope_id', $this->scope->getKey())
                ->first();

            if ($scopedRole) {
                return $scopedRole;
            }
        }

        return Role::query()
            ->where('name', $roleName)
            ->whereNull('scope_type')
            ->whereNull('scope_id')
            ->first();
    }

    public function assignRole(BackedEnum|string|Role $role): self
    {
        $role = $this->role($role);

        Assignment::query()->firstOrCreate($this->assignmentAttributes(['role_id' => $role->getKey()]));
        app(AccessCache::class)->forget($this->actor, $this->scope);

        return $this;
    }

    public function removeRole(BackedEnum|string|Role $role): self
    {
        Assignment::query()
            ->where($this->assignmentAttributes(['role_id' => $this->role($role)->getKey()]))
            ->delete();

        app(AccessCache::class)->forget($this->actor, $this->scope);

        return $this;
    }

    public function hasRole(BackedEnum|string|Role $role): bool
    {
        return Assignment::query()
            ->where($this->assignmentAttributes(['role_id' => $this->role($role)->getKey()]))
            ->exists();
    }

    /** @param array<BackedEnum|string|Role> $roles */
    public function hasAnyRole(array $roles): bool
    {
        return array_any($roles, fn (\BackedEnum|string|Role $role): bool => $this->hasRole($role));
    }

    public function can(BackedEnum|string $permission): bool
    {
        return app(AccessChecker::class)->can($this->actor, $this->scope, $this->permissionName($permission));
    }

    public function cannot(BackedEnum|string $permission): bool
    {
        return ! $this->can($permission);
    }

    public function hasPermission(BackedEnum|string $permission): bool
    {
        return $this->can($permission);
    }

    public function givePermission(BackedEnum|string $permission): self
    {
        $permission = Permission::query()->firstOrCreate(['name' => $this->permissionName($permission)]);

        Assignment::query()->firstOrCreate($this->assignmentAttributes(['permission_id' => $permission->getKey()]));
        app(AccessCache::class)->forget($this->actor, $this->scope);

        return $this;
    }

    public function revokePermission(BackedEnum|string $permission): self
    {
        $permission = Permission::query()->where('name', $this->permissionName($permission))->first();

        if ($permission) {
            Assignment::query()
                ->where($this->assignmentAttributes(['permission_id' => $permission->getKey()]))
                ->delete();
        }

        app(AccessCache::class)->forget($this->actor, $this->scope);

        return $this;
    }

    /**
     * @param  iterable<BackedEnum|string>  $permissions
     * @return array<string, bool>
     */
    public function toArray(iterable $permissions): array
    {
        $map = [];

        foreach ($permissions as $permission) {
            $name = $this->permissionName($permission);
            $map[$name] = $this->can($name);
        }

        return $map;
    }

    /** @return array<int, string> */
    public function permissions(): array
    {
        return app(AccessChecker::class)->permissionsFor($this->actor, $this->scope);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function assignmentAttributes(array $attributes): array
    {
        return array_merge([
            'actor_type' => $this->actor->getMorphClass(),
            'actor_id' => $this->actor->getKey(),
            'scope_type' => $this->scope?->getMorphClass(),
            'scope_id' => $this->scope?->getKey(),
        ], $attributes);
    }

    private function role(BackedEnum|string|Role $role): Role
    {
        if ($role instanceof Role) {
            return $role;
        }

        $roleName = $role instanceof BackedEnum ? $role->value : $role;

        if ($this->scope instanceof Model) {
            $scopedRole = Role::query()
                ->where('name', $roleName)
                ->where('scope_type', $this->scope->getMorphClass())
                ->where('scope_id', $this->scope->getKey())
                ->first();

            if ($scopedRole) {
                return $scopedRole;
            }
        }

        $globalRole = Role::query()
            ->where('name', $roleName)
            ->whereNull('scope_type')
            ->whereNull('scope_id')
            ->first();

        if ($globalRole) {
            return $globalRole;
        }

        return Role::query()->create([
            'name' => $roleName,
            'scope_type' => $this->scope?->getMorphClass(),
            'scope_id' => $this->scope?->getKey(),
            'is_global' => ! $this->scope instanceof Model,
            'is_system' => true,
        ]);
    }

    private function permissionName(BackedEnum|string $permission): string
    {
        return app(PermissionNormalizer::class)->normalize($permission);
    }
}
