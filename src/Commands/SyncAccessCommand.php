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
        $permissionNames = [];
        $permissionMetadata = $this->configuredPermissionMetadata($normalizer, $permissionNames);
        $roleMap = $this->configuredRoles($normalizer);
        $permissionNames = array_values(array_unique($permissionNames));
        foreach ($roleMap as $role => $permissions) {
            foreach ($permissions as $permission) {
                if (! in_array($permission, $permissionNames, true)) {
                    throw new InvalidArgumentException("Role [{$role}] references unknown permission [{$permission}]. Add it to a configured permission enum.");
                }
            }
        }
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
            if (array_key_exists($name, $permissionMetadata)) {
                $permission->setMetadata($permissionMetadata[$name]);
            }
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

    /**
     * @param  array<int, string>  $permissionNames
     * @return array<string, PermissionMetadata|null>
     */
    private function configuredPermissionMetadata(PermissionNormalizer $normalizer, array &$permissionNames): array
    {
        $permissions = [];

        $enums = config('access.permission_enums', []);
        if (! is_array($enums)) {
            throw new InvalidArgumentException('The permission_enums configuration must be an array.');
        }

        foreach ($enums as $index => $enum) {
            if (! is_string($enum) || ! enum_exists($enum) || ! is_subclass_of($enum, BackedEnum::class)) {
                throw new InvalidArgumentException("Configured permission enum [{$index}] must be a backed enum class.");
            }
            foreach ($enum::cases() as $case) {
                if (! is_string($case->value) || $case->value === '') {
                    throw new InvalidArgumentException("Permission enum [{$enum}] must contain non-empty string values.");
                }
                $permissionNames[] = $normalizer->normalize($case);
                $this->addEnumPermissionMetadata($permissions, $case, $normalizer);
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
        if (! $permission instanceof DescribesPermission) {
            return;
        }

        $metadata = $permission->metadata();

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
        $scoped = config('access.roles', []);
        $global = config('access.global_roles', []);
        if (! is_array($scoped) || ! is_array($global)) {
            throw new InvalidArgumentException('The roles and global_roles configurations must be arrays.');
        }
        $duplicates = array_intersect(array_keys($scoped), array_keys($global));
        if ($duplicates !== []) {
            throw new InvalidArgumentException('A role cannot be both scoped and global: ['.reset($duplicates).'].');
        }

        foreach (array_merge($scoped, $global) as $role => $permissions) {
            if (! is_string($role) || $role === '' || ! is_array($permissions)) {
                throw new InvalidArgumentException('Each configured role must have a name and an array of permissions.');
            }

            $normalizedPerms = [];
            foreach ($permissions as $perm) {
                if (! is_string($perm) && ! ($perm instanceof BackedEnum && is_string($perm->value))) {
                    throw new InvalidArgumentException("Role [{$role}] must reference permissions by name or string-backed enum case.");
                }
                $normalizedPerms[] = $perm;
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
