<?php

use Maxiviper117\Access\Tests\Fixtures\Company;
use Maxiviper117\Access\Tests\Fixtures\Permission;
use Maxiviper117\Access\Tests\Fixtures\User;

it('supports global roles and permissions', function (): void {
    syncTestRole('Platform Admin', [Permission::SystemManage], global: true);

    $user = User::query()->create(['email' => 'david@example.com']);

    $user->assignGlobalRole('Platform Admin');

    expect($user->hasGlobalRole('Platform Admin'))->toBeTrue()
        ->and($user->canGlobally(Permission::SystemManage))->toBeTrue();
});

it('revokes global direct permissions without changing scoped grants', function (): void {
    $user = User::query()->create(['email' => 'david@example.com']);
    $company = Company::query()->create(['name' => 'Acme']);

    $user->giveGlobalPermission(Permission::SystemManage);
    $user->in($company)->givePermission(Permission::SystemManage);

    expect($user->canGlobally(Permission::SystemManage))->toBeTrue()
        ->and($user->in($company)->can(Permission::SystemManage))->toBeTrue();

    $user->revokeGlobalPermission(Permission::SystemManage);

    expect($user->canGlobally(Permission::SystemManage))->toBeFalse()
        ->and($user->in($company)->can(Permission::SystemManage))->toBeTrue();
});
