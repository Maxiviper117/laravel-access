<?php

namespace Maxiviper117\Access\Commands;

use BackedEnum;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Maxiviper117\Access\Contracts\DescribesPermission;
use Maxiviper117\Access\Data\PermissionMetadata;
use Maxiviper117\Access\Models\Permission;
use Maxiviper117\Access\Models\Role;
use Maxiviper117\Access\Support\AccessCache;
use Maxiviper117\Access\Support\PermissionNormalizer;

class SyncAccessCommand extends Command
{
    protected $signature = 'access:sync {--dry-run : Show changes without writing} {--prune : Delete stale permissions and roles} {--force : Skip confirmation when pruning}';

    protected $description = 'Sync configured Laravel Access permissions and roles.';

    public function handle(PermissionNormalizer $normalizer, AccessCache $cache): int
    {
        $permissionMetadata = $this->configuredPermissionMetadata($normalizer);
        $permissionNames = array_keys($permissionMetadata);
        $roleMap = $this->configuredRoles($normalizer);
        $configuredRoles = array_keys($roleMap);
        $dryRun = (bool) $this->option('dry-run');

        $existingPermissions = Permission::query()
            ->get(['name', 'is_system', 'resource', 'kind', 'target', 'ability'])
            ->keyBy('name');
        $existingPermissionNames = $existingPermissions->keys()->map(fn ($name): string => (string) $name)->all();
        $systemPermissionNames = $existingPermissions
            ->filter(fn (Permission $permission): bool => $permission->is_system)
            ->keys()
            ->map(fn ($name): string => (string) $name)
            ->all();
        $missingPermissions = array_values(array_diff($permissionNames, $existingPermissionNames));
        $permissionsToMarkAsSystem = array_values(array_intersect($permissionNames, array_diff($existingPermissionNames, $systemPermissionNames)));
        $stalePermissions = array_values(array_diff($systemPermissionNames, $permissionNames));
        $metadataChanges = $this->permissionsWithChangedMetadata($permissionMetadata, $existingPermissions->all());
        $systemRolesQuery = Role::query()->where('is_system', true)->whereNull('scope_type')->whereNull('scope_id');
        $systemRolesInDb = array_map(
            fn ($v): string => match (true) {
                is_string($v) => $v,
                is_scalar($v) || is_null($v) => (string) $v,
                default => '',
            },
            $systemRolesQuery->pluck('name')->all(),
        );

        $missingRoles = array_values(array_diff($configuredRoles, $systemRolesInDb));
        $staleRoles = array_values(array_diff($systemRolesInDb, $configuredRoles));

        $this->report('Permissions will be created', $missingPermissions);
        $this->report('Permissions will be marked as system-defined', $permissionsToMarkAsSystem);
        $this->report('Permission metadata will change', $metadataChanges);
        $this->report('Stale system permissions', $stalePermissions);
        $this->report('Roles will be created', $missingRoles);
        $this->report('Roles not configured', $staleRoles);

        if ($dryRun) {
            return self::SUCCESS;
        }

        foreach ($permissionNames as $name) {
            $permission = Permission::query()->firstOrCreate(['name' => $name]);

            $permission->is_system = true;
            $permission->setMetadata($permissionMetadata[$name]);
            $permission->save();
        }

        foreach ($roleMap as $roleName => $permissions) {
            $role = Role::query()
                ->where('name', $roleName)
                ->whereNull('scope_type')
                ->whereNull('scope_id')
                ->first();

            if ($role) {
                $role->update([
                    'is_global' => in_array($roleName, array_keys((array) config('access.global_roles', [])), true),
                    'is_system' => true,
                ]);
            } else {
                $role = Role::query()->create([
                    'name' => $roleName,
                    'is_global' => in_array($roleName, array_keys((array) config('access.global_roles', [])), true),
                    'is_system' => true,
                ]);
            }

            $ids = Permission::query()->whereIn('name', $permissions)->pluck('id')->all();
            $role->permissions()->sync($ids);
        }

        if ($this->option('prune') && ($this->option('force') || $this->confirm('Delete stale permissions and roles?'))) {
            Permission::query()->where('is_system', true)->whereIn('name', $stalePermissions)->delete();
            Role::query()
                ->where('is_system', true)
                ->whereNull('scope_type')
                ->whereNull('scope_id')
                ->whereIn('name', $staleRoles)
                ->delete();
        }

        $cache->clear();
        $this->info('Access permissions and roles synced.');

        return self::SUCCESS;
    }

    /** @return array<string, PermissionMetadata|null> */
    private function configuredPermissionMetadata(PermissionNormalizer $normalizer): array
    {
        $permissions = [];

        foreach ((array) config('access.permission_enums', []) as $enum) {
            if (! is_string($enum)) {
                continue;
            }
            if (! enum_exists($enum)) {
                continue;
            }
            foreach ($enum::cases() as $case) {
                if ($case instanceof BackedEnum) {
                    $this->addEnumPermissionMetadata($permissions, $case, $normalizer);
                }
            }
        }

        foreach (array_merge((array) config('access.roles', []), (array) config('access.global_roles', [])) as $rolePermissions) {
            foreach ((array) $rolePermissions as $permission) {
                if ($permission instanceof BackedEnum) {
                    $this->addEnumPermissionMetadata($permissions, $permission, $normalizer);
                } elseif (is_string($permission)) {
                    $name = $normalizer->normalize($permission);
                    $permissions[$name] ??= null;
                }
            }
        }

        return $permissions;
    }

    /**
     * @param  array<string, PermissionMetadata|null>  $permissions
     */
    private function addEnumPermissionMetadata(array &$permissions, BackedEnum $permission, PermissionNormalizer $normalizer): void
    {
        $name = $normalizer->normalize($permission);
        $metadata = $permission instanceof DescribesPermission ? $permission->metadata() : null;

        if (array_key_exists($name, $permissions) && $this->metadataValues($permissions[$name]) !== $this->metadataValues($metadata)) {
            throw new InvalidArgumentException("Conflicting metadata is configured for the [{$name}] permission.");
        }

        $permissions[$name] = $metadata;
    }

    /** @return array{resource: string|null, kind: string|null, target: string|null, ability: string|null} */
    private function metadataValues(?PermissionMetadata $metadata): array
    {
        return $metadata?->toArray() ?? [
            'resource' => null,
            'kind' => null,
            'target' => null,
            'ability' => null,
        ];
    }

    /**
     * @param  array<string, PermissionMetadata|null>  $configuredMetadata
     * @param  array<array-key, Permission>  $existingPermissions
     * @return array<int, string>
     */
    private function permissionsWithChangedMetadata(array $configuredMetadata, array $existingPermissions): array
    {
        $changed = [];

        foreach ($configuredMetadata as $name => $metadata) {
            $existing = $existingPermissions[$name] ?? null;

            if ($existing === null && $metadata === null) {
                continue;
            }

            if ($existing === null || $existing->only(['resource', 'kind', 'target', 'ability']) !== $this->metadataValues($metadata)) {
                $changed[] = $name;
            }
        }

        return $changed;
    }

    /** @return array<string, array<int, string>> */
    private function configuredRoles(PermissionNormalizer $normalizer): array
    {
        $roles = [];

        foreach (array_merge((array) config('access.roles', []), (array) config('access.global_roles', [])) as $role => $permissions) {
            if (! is_string($role)) {
                continue;
            }

            $normalizedPerms = [];
            foreach ((array) $permissions as $perm) {
                if ($perm instanceof BackedEnum || is_string($perm)) {
                    $normalizedPerms[] = $perm;
                }
            }
            $roles[$role] = $normalizer->normalizeMany($normalizedPerms);
        }

        return $roles;
    }

    /** @param array<int, string> $items */
    private function report(string $title, array $items): void
    {
        if ($items === []) {
            return;
        }

        $this->warn($title.':');

        foreach ($items as $item) {
            $this->line('- '.$item);
        }
    }
}
