<?php

use Maxiviper117\Access\Facades\Access;
use Maxiviper117\Access\Models\Assignment;
use Maxiviper117\Access\Models\Permission as PermissionModel;
use Maxiviper117\Access\Models\Role;
use Maxiviper117\Access\Tests\Fixtures\CompanyPermission;
use Maxiviper117\Access\Tests\Fixtures\Permission;
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
