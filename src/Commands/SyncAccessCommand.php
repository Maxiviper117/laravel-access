<?php

namespace Maxiviper117\Access\Commands;

use BackedEnum;
use Illuminate\Console\Command;
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
        $permissionNames = $this->configuredPermissionNames($normalizer);
        $roleMap = $this->configuredRoles($normalizer);
        $configuredRoles = array_keys($roleMap);
        $dryRun = (bool) $this->option('dry-run');

        $missingPermissions = array_values(array_diff($permissionNames, Permission::query()->pluck('name')->all()));
        $stalePermissions = array_values(array_diff(Permission::query()->pluck('name')->all(), $permissionNames));
        $systemRolesQuery = Role::query()->where('is_system', true)->whereNull('scope_type')->whereNull('scope_id');
        $systemRolesInDb = $systemRolesQuery->pluck('name')->all();

        $missingRoles = array_values(array_diff($configuredRoles, $systemRolesInDb));
        $staleRoles = array_values(array_diff($systemRolesInDb, $configuredRoles));

        $this->report('Permissions will be created', $missingPermissions);
        $this->report('Permissions not configured', $stalePermissions);
        $this->report('Roles will be created', $missingRoles);
        $this->report('Roles not configured', $staleRoles);

        if ($dryRun) {
            return self::SUCCESS;
        }

        foreach ($permissionNames as $name) {
            Permission::query()->firstOrCreate(['name' => $name]);
        }

        foreach ($roleMap as $roleName => $permissions) {
            $role = Role::query()
                ->where('name', $roleName)
                ->whereNull('scope_type')
                ->whereNull('scope_id')
                ->first();

            if ($role) {
                $role->update([
                    'is_global' => in_array($roleName, array_keys(config('access.global_roles', [])), true),
                    'is_system' => true,
                ]);
            } else {
                $role = Role::query()->create([
                    'name' => $roleName,
                    'is_global' => in_array($roleName, array_keys(config('access.global_roles', [])), true),
                    'is_system' => true,
                ]);
            }

            $ids = Permission::query()->whereIn('name', $permissions)->pluck('id')->all();
            $role->permissions()->sync($ids);
        }

        if ($this->option('prune') && ($this->option('force') || $this->confirm('Delete stale permissions and roles?'))) {
            Permission::query()->whereIn('name', $stalePermissions)->delete();
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

    private function configuredPermissionNames(PermissionNormalizer $normalizer): array
    {
        $permissions = [];
        foreach (config('access.permission_enums', []) as $enum) {
            if (! is_string($enum) || ! enum_exists($enum)) {
                continue;
            }

            foreach ($enum::cases() as $case) {
                if ($case instanceof BackedEnum) {
                    $permissions[] = $normalizer->normalize($case);
                }
            }
        }

        foreach ($this->configuredRoles($normalizer) as $rolePermissions) {
            array_push($permissions, ...$rolePermissions);
        }

        return array_values(array_unique($permissions));
    }

    private function configuredRoles(PermissionNormalizer $normalizer): array
    {
        $roles = [];

        foreach (array_merge(config('access.roles', []), config('access.global_roles', [])) as $role => $permissions) {
            $roles[$role] = $normalizer->normalizeMany($permissions);
        }

        return $roles;
    }

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
