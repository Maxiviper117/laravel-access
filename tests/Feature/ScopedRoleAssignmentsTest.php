<?php

use Maxiviper117\Access\Facades\Access;
use Maxiviper117\Access\Tests\Fixtures\Company;
use Maxiviper117\Access\Tests\Fixtures\Permission;
use Maxiviper117\Access\Tests\Fixtures\User;

it('assigns scoped roles and checks permissions without leaking scopes', function (): void {
    Access::role('Owner')->allows([Permission::UsersInvite]);

    $user = User::query()->create(['email' => 'david@example.com']);
    $company = Company::query()->create(['name' => 'A']);
    $otherCompany = Company::query()->create(['name' => 'B']);

    $user->in($company)->assignRole('Owner');

    expect($user->in($company)->hasRole('Owner'))->toBeTrue()
        ->and($user->in($company)->can(Permission::UsersInvite))->toBeTrue()
        ->and($user->in($otherCompany)->can(Permission::UsersInvite))->toBeFalse();
});
