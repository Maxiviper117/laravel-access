<?php

use Maxiviper117\Access\Models\Permission as PermissionModel;
use Maxiviper117\Access\Models\Role;
use Maxiviper117\Access\Tests\Fixtures\CompanyPermission;
use Maxiviper117\Access\Tests\Fixtures\Permission;

it('syncs permissions and configured roles', function () {
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
        ->and(Role::query()->where('name', 'Owner')->first()->permissions->pluck('name')->all())->toContain('users.invite')
        ->and(Role::query()->where('name', 'Platform Admin')->first()->is_global)->toBeTrue();
});

it('syncs permissions from multiple configured enums', function () {
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
