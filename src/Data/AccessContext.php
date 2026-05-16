<?php

namespace Maxiviper117\Access\Data;

use BackedEnum;
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

    public function assignRole(string|Role $role): self
    {
        $role = $this->role($role);

        Assignment::query()->firstOrCreate($this->assignmentAttributes(['role_id' => $role->getKey()]));
        app(AccessCache::class)->forget($this->actor, $this->scope);

        return $this;
    }

    public function removeRole(string|Role $role): self
    {
        Assignment::query()
            ->where($this->assignmentAttributes(['role_id' => $this->role($role)->getKey()]))
            ->delete();

        app(AccessCache::class)->forget($this->actor, $this->scope);

        return $this;
    }

    public function hasRole(string|Role $role): bool
    {
        return Assignment::query()
            ->where($this->assignmentAttributes(['role_id' => $this->role($role)->getKey()]))
            ->exists();
    }

    public function hasAnyRole(array $roles): bool
    {
        foreach ($roles as $role) {
            if ($this->hasRole($role)) {
                return true;
            }
        }

        return false;
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

    public function toArray(iterable $permissions): array
    {
        $map = [];

        foreach ($permissions as $permission) {
            $name = $this->permissionName($permission);
            $map[$name] = $this->can($name);
        }

        return $map;
    }

    public function permissions(): array
    {
        return app(AccessChecker::class)->permissionsFor($this->actor, $this->scope);
    }

    private function assignmentAttributes(array $attributes): array
    {
        return array_merge([
            'actor_type' => $this->actor->getMorphClass(),
            'actor_id' => $this->actor->getKey(),
            'scope_type' => $this->scope?->getMorphClass(),
            'scope_id' => $this->scope?->getKey(),
        ], $attributes);
    }

    private function role(string|Role $role): Role
    {
        return $role instanceof Role ? $role : Role::query()->firstOrCreate(['name' => $role]);
    }

    private function permissionName(BackedEnum|string $permission): string
    {
        return app(PermissionNormalizer::class)->normalize($permission);
    }
}
