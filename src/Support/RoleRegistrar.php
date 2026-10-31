<?php

namespace Maxiviper117\Access\Support;

use BackedEnum;
use Maxiviper117\Access\Contracts\DescribesPermission;
use Maxiviper117\Access\Models\Permission;
use Maxiviper117\Access\Models\Role;

class RoleRegistrar
{
    public function __construct(
        private readonly string $name,
        private readonly bool $global = false,
    ) {}

    /** @param array<BackedEnum|string> $permissions */
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
            ->map(function (BackedEnum|string $permission): int {
                $name = app(PermissionNormalizer::class)->normalize($permission);
                $permissionRecord = Permission::query()->firstOrCreate(['name' => $name]);

                $permissionRecord->is_system = true;
                if ($permission instanceof DescribesPermission) {
                    $permissionRecord->setMetadata($permission->metadata());
                }
                $permissionRecord->save();

                $key = $permissionRecord->getKey();

                return is_scalar($key) ? (int) $key : 0;
            })
            ->all();

        $role->permissions()->sync($ids);

        app(AccessCache::class)->clear();

        return $role->refresh();
    }
}
