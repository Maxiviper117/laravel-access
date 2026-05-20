<?php

namespace Maxiviper117\Access;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Maxiviper117\Access\Data\AccessContext;
use Maxiviper117\Access\Models\Role;
use Maxiviper117\Access\Support\RoleRegistrar;

class Access
{
    public function for(Model $actor): AccessContext
    {
        return new AccessContext($actor);
    }

    public function role(string $name, bool $global = false): RoleRegistrar
    {
        return new RoleRegistrar($name, $global);
    }

    public function defineScopedGates(string $permissionEnum, string $scopeClass): void
    {
        if (! enum_exists($permissionEnum)) {
            return;
        }

        foreach ($permissionEnum::cases() as $permission) {
            if (! $permission instanceof \BackedEnum || ! is_string($permission->value)) {
                continue;
            }

            Gate::define($permission->value, fn (Model $user, Model $scope): bool => $scope instanceof $scopeClass
                && $this->for($user)->in($scope)->can($permission));
        }
    }

    public function findRole(string $name): Role
    {
        return Role::query()
            ->where('name', $name)
            ->whereNull('scope_type')
            ->whereNull('scope_id')
            ->first() ?? Role::query()->create([
                'name' => $name,
                'is_global' => true,
                'is_system' => true,
            ]);
    }
}
