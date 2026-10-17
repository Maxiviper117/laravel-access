<?php

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

it('syncs metadata for enum cases referenced by configured roles without listing their enum', function (): void {
    config()->set('access.permission_enums', []);
    config()->set('access.roles', [
        'Invoice Analyst' => [StructuredPermission::InvoicesView],
    ]);
    config()->set('access.global_roles', []);

    $this->artisan('access:sync')->assertSuccessful();

    $permission = PermissionModel::query()->where('name', 'invoices.view')->firstOrFail();

    expect($permission->resource)->toBe('invoices')
        ->and($permission->kind)->toBe('resource')
        ->and($permission->ability)->toBe('view');
});

it('keeps permissions configured only as strings unclassified', function (): void {
    config()->set('access.permission_enums', []);
    config()->set('access.roles', [
        'Legacy Viewer' => ['legacy.invoices.view'],
    ]);
    config()->set('access.global_roles', []);

    $this->artisan('access:sync')->assertSuccessful();

    $permission = PermissionModel::query()->where('name', 'legacy.invoices.view')->firstOrFail();

    expect($permission->is_system)->toBeTrue()
        ->and($permission->resource)->toBeNull()
        ->and($permission->kind)->toBeNull()
        ->and($permission->target)->toBeNull()
        ->and($permission->ability)->toBeNull();
});

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
    Access::role('Legacy Viewer')->allows([Permission::UsersView]);
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
