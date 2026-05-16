<?php

use Maxiviper117\Access\Facades\Access;
use Maxiviper117\Access\Tests\Fixtures\Permission;
use Maxiviper117\Access\Tests\Fixtures\User;

it('supports global roles and permissions', function () {
    Access::role('Platform Admin', global: true)->allows([Permission::SystemManage]);

    $user = User::query()->create(['email' => 'david@example.com']);

    $user->assignGlobalRole('Platform Admin');

    expect($user->hasGlobalRole('Platform Admin'))->toBeTrue()
        ->and($user->canGlobally(Permission::SystemManage))->toBeTrue();
});
