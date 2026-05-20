<?php

use Maxiviper117\Access\Facades\Access;
use Maxiviper117\Access\Tests\Fixtures\Company;
use Maxiviper117\Access\Tests\Fixtures\Permission;
use Maxiviper117\Access\Tests\Fixtures\User;

beforeEach(function (): void {
    config()->set('access.default_scope_model', Company::class);
});

it('returns failure and shows error when user is not found', function (): void {
    $this->artisan('access:debug 9999')
        ->expectsOutputToContain('User not found.')
        ->assertFailed();
});

it('displays global roles and permissions when no scope is provided', function (): void {
    Access::role('Admin', global: true)->allows([Permission::SystemManage]);

    $user = User::query()->create([
        'name' => 'David',
        'email' => 'david@example.com',
    ]);

    $user->assignGlobalRole('Admin');

    $this->artisan("access:debug {$user->id}")
        ->expectsOutputToContain('User: David <david@example.com>')
        ->expectsOutputToContain('Scope: global')
        ->expectsOutputToContain('Roles:')
        ->expectsOutputToContain('- Admin')
        ->expectsOutputToContain('Permissions:')
        ->expectsOutputToContain('- system.manage')
        ->assertSuccessful();

    // Verify lookup by email works too
    $this->artisan('access:debug david@example.com')
        ->assertSuccessful();
});

it('displays scoped roles and permissions when scope option is provided', function (): void {
    Access::role('Owner')->allows([Permission::UsersInvite]);

    $user = User::query()->create([
        'name' => 'David',
        'email' => 'david@example.com',
    ]);
    $company = Company::query()->create(['name' => 'Acme']);

    $user->in($company)->assignRole('Owner');

    $this->artisan("access:debug {$user->id} --scope=company:{$company->id}")
        ->expectsOutputToContain('User: David <david@example.com>')
        ->expectsOutputToContain("Scope: Company #{$company->id}")
        ->expectsOutputToContain('Roles:')
        ->expectsOutputToContain('- Owner')
        ->expectsOutputToContain('Permissions:')
        ->expectsOutputToContain('- users.invite')
        ->assertSuccessful();
});
