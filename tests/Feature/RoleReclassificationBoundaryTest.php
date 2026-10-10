<?php

use Maxiviper117\Access\Facades\Access;
use Maxiviper117\Access\Tests\Fixtures\Company;
use Maxiviper117\Access\Tests\Fixtures\Permission;
use Maxiviper117\Access\Tests\Fixtures\User;

it('does not grant a global role through an existing scoped assignment', function (): void {
    $user = User::query()->create(['email' => 'david@example.com']);
    $company = Company::query()->create(['name' => 'Acme']);

    Access::role('Auditor')->allows([Permission::UsersInvite]);
    $user->in($company)->assignRole('Auditor');

    expect($user->in($company)->can(Permission::UsersInvite))->toBeTrue();

    Access::role('Auditor', global: true)->allows([Permission::SystemManage]);

    expect($user->in($company)->can(Permission::SystemManage))->toBeFalse();
});
