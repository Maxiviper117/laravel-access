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
        $role = Role::query()
            ->where('name', $this->name)
            ->whereNull('scope_type')
            ->whereNull('scope_id')
            ->first() ?? Role::query()->create([
                'name' => $this->name,
                'is_global' => $this->global,
                'is_system' => true,
            ]);

        $role->update([
            'is_global' => $this->global,
            'is_system' => true,
        ]);

        $ids = collect($permissions)
            ->map(fn (BackedEnum|string $permission): string => app(PermissionNormalizer::class)->normalize($permission))
            ->map(fn (string $name): int => Permission::query()->firstOrCreate(['name' => $name])->getKey())
            ->all();

        $role->permissions()->sync($ids);

        app(AccessCache::class)->clear();

        return $role->refresh();
    }
}
