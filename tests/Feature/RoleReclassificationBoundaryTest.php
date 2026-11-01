<?php

use Maxiviper117\Access\Tests\Fixtures\Company;
use Maxiviper117\Access\Tests\Fixtures\Permission;
use Maxiviper117\Access\Tests\Fixtures\User;

it('does not grant a global role through an existing scoped assignment', function (): void {
    $user = User::query()->create(['email' => 'david@example.com']);
    $company = Company::query()->create(['name' => 'Acme']);

    syncTestRole('Auditor', [Permission::UsersInvite]);
    $user->in($company)->assignRole('Auditor');

    expect($user->in($company)->can(Permission::UsersInvite))->toBeTrue();

    syncTestRole('Auditor', [Permission::SystemManage], global: true);

    expect($user->in($company)->can(Permission::SystemManage))->toBeFalse();
});
