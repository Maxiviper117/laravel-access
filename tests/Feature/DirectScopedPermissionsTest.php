<?php

use Maxiviper117\Access\Tests\Fixtures\Company;
use Maxiviper117\Access\Tests\Fixtures\Permission;
use Maxiviper117\Access\Tests\Fixtures\User;

it('supports direct scoped permissions and inertia style maps', function (): void {
    $user = User::query()->create(['email' => 'david@example.com']);
    $company = Company::query()->create(['name' => 'A']);

    $user->in($company)->givePermission(Permission::UsersInvite);

    expect($user->in($company)->toArray([
        Permission::UsersInvite,
        Permission::RolesManage,
    ]))->toBe([
        'users.invite' => true,
        'roles.manage' => false,
    ]);
});
