<?php

namespace Maxiviper117\Access\Support;

use BackedEnum;
use Maxiviper117\Access\Models\Permission;
use Maxiviper117\Access\Models\Role;

class RoleRegistrar
{
    public function __construct(
        private readonly string $name,
        private readonly bool $global = false,
    ) {}

    public function allows(array $permissions): Role
    {
        $role = Role::query()->updateOrCreate(
            ['name' => $this->name],
            ['is_global' => $this->global],
        );

        $ids = collect($permissions)
            ->map(fn (BackedEnum|string $permission): string => app(PermissionNormalizer::class)->normalize($permission))
            ->map(fn (string $name): int => Permission::query()->firstOrCreate(['name' => $name])->getKey())
            ->all();

        $role->permissions()->sync($ids);

        app(AccessCache::class)->clear();

        return $role->refresh();
    }
}
