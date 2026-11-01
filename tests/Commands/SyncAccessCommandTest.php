<?php

use Maxiviper117\Access\Contracts\DescribesPermission;
use Maxiviper117\Access\Data\PermissionMetadata;
use Maxiviper117\Access\Facades\Access;
use Maxiviper117\Access\Models\Assignment;
use Maxiviper117\Access\Models\Permission as PermissionModel;
use Maxiviper117\Access\Models\Role;
use Maxiviper117\Access\Tests\Fixtures\CompanyPermission;
use Maxiviper117\Access\Tests\Fixtures\ConflictingStructuredPermission;
use Maxiviper117\Access\Tests\Fixtures\Permission;
use Maxiviper117\Access\Tests\Fixtures\StructuredPermission;
use Maxiviper117\Access\Tests\Fixtures\User;

enum DuplicatePermission: string implements DescribesPermission
{
    case InvoicesView = 'invoices.view';
    case InvoicesExport = 'invoices.export';

    public function metadata(): ?PermissionMetadata
    {
        return match ($this) {
            self::InvoicesView => PermissionMetadata::resource('invoices', 'view'),
            self::InvoicesExport => null,
        };
    }
}

enum NullViewPermission: string implements DescribesPermission
{
    case InvoicesView = 'invoices.view';

    public function metadata(): ?PermissionMetadata
    {
        return null;
    }
}

enum InvalidPermission: int
{
    case View = 1;
}

it('syncs permissions and configured roles', function (): void {
    config()->set('access.permission_enums', [Permission::class]);
    config()->set('access.roles', [
        'Owner' => [Permission::UsersView, Permission::UsersInvite, Permission::RolesManage],
        'Member' => [Permission::UsersView],
    ]);
    config()->set('access.global_roles', [
        'Platform Admin' => [Permission::SystemManage],
    ]);

    $this->artisan('access:sync')->assertSuccessful();

    expect(PermissionModel::query()->pluck('name')->all())->toContain('users.view', 'users.invite', 'roles.manage', 'system.manage')
        ->and(PermissionModel::query()->where('name', 'users.view')->first()->is_system)->toBeTrue()
        ->and(Role::query()->where('name', 'Owner')->first()->permissions->pluck('name')->all())->toContain('users.invite')
        ->and(Role::query()->where('name', 'Platform Admin')->first()->is_global)->toBeTrue();
});

it('syncs permissions from multiple configured enums', function (): void {
    config()->set('access.permission_enums', [
        Permission::class,
        CompanyPermission::class,
    ]);
    config()->set('access.roles', [
        'Owner' => [CompanyPermission::MembersInvite],
    ]);
    config()->set('access.global_roles', []);

    $this->artisan('access:sync')->assertSuccessful();

    expect(PermissionModel::query()->pluck('name')->all())
        ->toContain('users.view', 'company.members.invite');
});

it('syncs metadata from configured enums and enum cases used by roles', function (): void {
    config()->set('access.permission_enums', [StructuredPermission::class]);
    config()->set('access.roles', [
        'Invoice Analyst' => [StructuredPermission::InternalCostView],
    ]);
    config()->set('access.global_roles', []);

    $this->artisan('access:sync')->assertSuccessful();

    $resourcePermission = PermissionModel::query()->where('name', 'invoices.view')->firstOrFail();
    $fieldPermission = PermissionModel::query()->where('name', 'invoices.fields.internal_cost.view')->firstOrFail();
    $unclassifiedPermission = PermissionModel::query()->where('name', 'invoices.export')->firstOrFail();

    expect($resourcePermission->resource)->toBe('invoices')
        ->and($resourcePermission->kind)->toBe('resource')
        ->and($resourcePermission->target)->toBeNull()
        ->and($resourcePermission->ability)->toBe('view')
        ->and($fieldPermission->resource)->toBe('invoices')
        ->and($fieldPermission->kind)->toBe('field')
        ->and($fieldPermission->target)->toBe('internal_cost')
        ->and($fieldPermission->ability)->toBe('view')
        ->and($unclassifiedPermission->resource)->toBeNull()
        ->and($unclassifiedPermission->kind)->toBeNull();
});

it('rejects enum cases not present in the configured catalog', function (): void {
    config()->set('access.permission_enums', []);
    config()->set('access.roles', [
        'Invoice Analyst' => [StructuredPermission::InvoicesView],
    ]);
    config()->set('access.global_roles', []);

    expect(fn () => $this->artisan('access:sync'))->toThrow(InvalidArgumentException::class, 'unknown permission [invoices.view]');
    expect(PermissionModel::query()->count())->toBe(0)->and(Role::query()->count())->toBe(0);
});

it('uses catalog metadata regardless of role references', function (): void {
    config()->set('access.permission_enums', [StructuredPermission::class]);
    config()->set('access.roles', [
        'First' => ['invoices.view'],
        'Second' => [ConflictingStructuredPermission::InvoicesView],
    ]);
    config()->set('access.global_roles', []);

    $this->artisan('access:sync')->assertSuccessful();

    expect(PermissionModel::query()->where('name', 'invoices.view')->firstOrFail()->resource)->toBe('invoices')
        ->and(Role::query()->where('name', 'Second')->firstOrFail()->permissions->pluck('name')->all())->toContain('invoices.view');
});

it('accepts identical declarations including null metadata', function (): void {
    config()->set('access.permission_enums', [StructuredPermission::class, DuplicatePermission::class]);
    config()->set('access.roles', []);
    config()->set('access.global_roles', []);

    $this->artisan('access:sync')->assertSuccessful();

    expect(PermissionModel::query()->where('name', 'invoices.view')->firstOrFail()->resource)->toBe('invoices')
        ->and(PermissionModel::query()->where('name', 'invoices.export')->firstOrFail()->resource)->toBeNull();
});

it('rejects null and non-null declarations before writing in normal and dry runs', function (string $command): void {
    config()->set('access.permission_enums', [StructuredPermission::class, NullViewPermission::class]);
    config()->set('access.roles', ['Viewer' => ['invoices.view']]);
    config()->set('access.global_roles', []);

    expect(fn () => $this->artisan($command))->toThrow(InvalidArgumentException::class, 'Conflicting metadata');
    expect(PermissionModel::query()->count())->toBe(0)
        ->and(Role::query()->count())->toBe(0);
})->with(['access:sync', 'access:sync --dry-run']);

it('rejects unknown string role permissions', function (): void {
    config()->set('access.permission_enums', []);
    config()->set('access.roles', [
        'Legacy Viewer' => ['legacy.invoices.view'],
    ]);
    config()->set('access.global_roles', []);

    expect(fn () => $this->artisan('access:sync'))->toThrow(InvalidArgumentException::class, 'unknown permission [legacy.invoices.view]');
    expect(PermissionModel::query()->count())->toBe(0)->and(Role::query()->count())->toBe(0);
});

it('rejects invalid enum configuration and role collisions before writes', function (array $enums, array $roles, array $globalRoles, string $message, string $command): void {
    $existing = Access::definePermission('existing.view');
    config()->set('access.permission_enums', $enums);
    config()->set('access.roles', $roles);
    config()->set('access.global_roles', $globalRoles);

    expect(fn () => $this->artisan($command))->toThrow(InvalidArgumentException::class, $message);
    expect(PermissionModel::query()->count())->toBe(1)
        ->and($existing->fresh()->is_system)->toBeFalse()
        ->and(Role::query()->count())->toBe(0);
})->with([
    [[Permission::class, 'Missing\\Permission'], [], [], 'backed enum class', 'access:sync --prune --force'],
    [[InvalidPermission::class], [], [], 'non-empty string values', 'access:sync'],
    [[Permission::class], ['Viewer' => [Permission::UsersView]], ['Viewer' => [Permission::UsersView]], 'both scoped and global', 'access:sync --dry-run'],
    [[Permission::class], ['Viewer' => ['users.typo']], [], 'unknown permission [users.typo]', 'access:sync --prune --force'],
    [[Permission::class], ['Viewer' => [42]], [], 'must reference permissions', 'access:sync'],
]);

it('leaves existing roles and grants unchanged when validation fails', function (string $command): void {
    $role = syncTestRole('Viewer', [Permission::UsersView]);
    $stale = syncTestRole('Stale', [Permission::UsersInvite]);
    $permissionIds = $role->permissions()->pluck('access_permissions.id')->all();
    config()->set('access.roles', ['Viewer' => [Permission::UsersInvite], 'Broken' => ['users.typo']]);

    expect(fn () => $this->artisan($command))->toThrow(InvalidArgumentException::class, 'users.typo');
    expect($role->fresh()->permissions()->pluck('access_permissions.id')->all())->toBe($permissionIds)
        ->and($stale->fresh())->not->toBeNull()
        ->and(Role::query()->where('name', 'Broken')->exists())->toBeFalse();
})->with(['access:sync', 'access:sync --dry-run', 'access:sync --prune --force']);

it('reports metadata changes in dry runs and applies them on sync', function (): void {
    $permission = PermissionModel::query()->create([
        'name' => 'invoices.view',
        'resource' => 'old-resource',
        'kind' => 'resource',
        'target' => null,
        'ability' => 'view',
    ]);
    config()->set('access.permission_enums', [StructuredPermission::class]);
    config()->set('access.roles', []);
    config()->set('access.global_roles', []);

    $this->artisan('access:sync --dry-run')
        ->expectsOutputToContain('Permission metadata will change:')
        ->expectsOutputToContain('- invoices.view')
        ->assertSuccessful();

    expect($permission->fresh()->resource)->toBe('old-resource');

    $this->artisan('access:sync')->assertSuccessful();

    expect($permission->fresh()->resource)->toBe('invoices');
});

it('clears stored metadata when the configured enum case returns null metadata', function (): void {
    $permission = Access::definePermission(
        'invoices.export',
        'Export invoices',
        metadata: PermissionMetadata::resource('invoices', 'export'),
    );
    config()->set('access.permission_enums', [StructuredPermission::class]);
    config()->set('access.roles', []);
    config()->set('access.global_roles', []);

    $this->artisan('access:sync')->assertSuccessful();

    expect($permission->fresh()->is_system)->toBeTrue()
        ->and($permission->fresh()->resource)->toBeNull()
        ->and($permission->fresh()->kind)->toBeNull()
        ->and($permission->fresh()->target)->toBeNull()
        ->and($permission->fresh()->ability)->toBeNull();
});

it('rejects conflicting metadata for the same configured permission name before syncing', function (): void {
    config()->set('access.permission_enums', [
        StructuredPermission::class,
        ConflictingStructuredPermission::class,
    ]);

    expect(fn () => $this->artisan('access:sync'))
        ->toThrow(InvalidArgumentException::class, 'Conflicting metadata is configured for the [invoices.view] permission.');

    expect(PermissionModel::query()->where('name', 'invoices.view')->exists())->toBeFalse();
});

it('does not change permission ownership during a dry run', function (): void {
    $permission = Access::definePermission('users.view', 'View users');
    config()->set('access.permission_enums', [Permission::class]);
    config()->set('access.roles', []);
    config()->set('access.global_roles', []);

    $this->artisan('access:sync --dry-run')->assertSuccessful();

    expect($permission->fresh()->is_system)->toBeFalse();

    $this->artisan('access:sync')->assertSuccessful();

    expect($permission->fresh()->is_system)->toBeTrue();
});

it('does not delete stale system permissions during a prune dry run', function (): void {
    syncTestRole('Legacy Viewer', [Permission::UsersView]);
    config()->set('access.permission_enums', []);
    config()->set('access.roles', []);
    config()->set('access.global_roles', []);

    $this->artisan('access:sync --prune --dry-run')->assertSuccessful();

    expect(PermissionModel::query()->where('name', 'users.view')->exists())->toBeTrue()
        ->and(Role::query()->where('name', 'Legacy Viewer')->exists())->toBeTrue();
});

it('prunes only stale system permissions and preserves dynamic definitions and grants', function (): void {
    config()->set('access.permission_enums', [Permission::class]);
    config()->set('access.roles', []);
    config()->set('access.global_roles', []);
    $this->artisan('access:sync')->assertSuccessful();

    $dynamicPermission = Access::definePermission(
        'reports.export',
        'Export reports',
        'Download report data.',
    );
    $user = User::query()->create(['email' => 'dynamic@example.com']);
    $user->giveGlobalPermission('reports.manage');
    $dynamicRole = $user->access()->createRole('report-manager');
    $user->access()->syncRolePermissions($dynamicRole, ['reports.export', 'reports.audit']);
    $user->access()->assignRole($dynamicRole);

    config()->set('access.permission_enums', []);
    $this->artisan('access:sync --prune --force')->assertSuccessful();

    expect(PermissionModel::query()->where('name', 'users.view')->exists())->toBeFalse()
        ->and($dynamicPermission->fresh()->is_system)->toBeFalse()
        ->and($dynamicPermission->fresh()->label)->toBe('Export reports')
        ->and($dynamicRole->fresh()->permissions->pluck('name')->all())->toContain('reports.export', 'reports.audit')
        ->and(PermissionModel::query()->where('name', 'reports.audit')->first()->is_system)->toBeFalse()
        ->and(Assignment::query()->where('permission_id', PermissionModel::query()->where('name', 'reports.manage')->value('id'))->exists())->toBeTrue()
        ->and($user->canGlobally('reports.manage'))->toBeTrue()
        ->and($user->canGlobally('reports.audit'))->toBeTrue();
});
