<?php

use Maxiviper117\Access\Facades\Access;
use Maxiviper117\Access\Models\Role;
use Maxiviper117\Access\Tests\Fixtures\Company;
use Maxiviper117\Access\Tests\Fixtures\Permission;
use Maxiviper117\Access\Tests\Fixtures\User;

it('supports cannot and hasPermission aliases', function (): void {
    $user = User::query()->create(['email' => 'david@example.com']);
    $company = Company::query()->create(['name' => 'Acme']);

    $user->in($company)->givePermission(Permission::UsersInvite);

    expect($user->in($company)->hasPermission(Permission::UsersInvite))->toBeTrue()
        ->and($user->in($company)->cannot(Permission::UsersInvite))->toBeFalse()
        ->and($user->in($company)->cannot(Permission::RolesManage))->toBeTrue();
});

it('supports hasAnyRole checks', function (): void {
    $user = User::query()->create(['email' => 'david@example.com']);
    $company = Company::query()->create(['name' => 'Acme']);

    $user->in($company)->assignRole('Editor');

    expect($user->in($company)->hasAnyRole(['Admin', 'Editor']))->toBeTrue()
        ->and($user->in($company)->hasAnyRole(['Admin', 'Viewer']))->toBeFalse();
});

it('can assign and check roles using Role models directly', function (): void {
    $user = User::query()->create(['email' => 'david@example.com']);
    $company = Company::query()->create(['name' => 'Acme']);

    $role = Role::query()->create(['name' => 'Moderator']);

    $user->in($company)->assignRole($role);

    expect($user->in($company)->hasRole($role))->toBeTrue()
        ->and($user->in($company)->hasRole('Moderator'))->toBeTrue();
});

it('can revoke direct permissions', function (): void {
    $user = User::query()->create(['email' => 'david@example.com']);
    $company = Company::query()->create(['name' => 'Acme']);

    $user->in($company)->givePermission(Permission::UsersInvite);
    expect($user->in($company)->can(Permission::UsersInvite))->toBeTrue();

    // Revoke
    $user->in($company)->revokePermission(Permission::UsersInvite);
    expect($user->in($company)->can(Permission::UsersInvite))->toBeFalse();

    // Revoking non-existent permission does not throw
    expect(function () use ($user, $company): void {
        $user->in($company)->revokePermission('non.existent.permission');
    })->not->toThrow(Exception::class);
});

it('can remove roles', function (): void {
    $user = User::query()->create(['email' => 'david@example.com']);
    $company = Company::query()->create(['name' => 'Acme']);

    $user->in($company)->assignRole('Editor');
    expect($user->in($company)->hasRole('Editor'))->toBeTrue();

    $user->in($company)->removeRole('Editor');
    expect($user->in($company)->hasRole('Editor'))->toBeFalse();
});

it('returns all unique permission names with permissions() method', function (): void {
    Access::role('Editor')->allows([Permission::UsersInvite, Permission::UsersView]);
    Access::role('Viewer')->allows([Permission::UsersView]);

    $user = User::query()->create(['email' => 'david@example.com']);
    $company = Company::query()->create(['name' => 'Acme']);

    $user->in($company)->assignRole('Editor');
    $user->in($company)->assignRole('Viewer');
    $user->in($company)->givePermission(Permission::RolesManage);

    // Roles have users.invite, users.view, plus direct roles.manage
    $permissions = $user->in($company)->permissions();

    expect($permissions)->toHaveCount(3)
        ->and($permissions)->toContain('users.invite', 'users.view', 'roles.manage');
});

it('supports HasAccess global trait helpers for removal and direct permissions', function (): void {
    $user = User::query()->create(['email' => 'david@example.com']);

    // Global direct permission
    $user->giveGlobalPermission(Permission::SystemManage);
    expect($user->canGlobally(Permission::SystemManage))->toBeTrue();

    // Global role assignment and removal
    $user->assignGlobalRole('Manager');
    expect($user->hasGlobalRole('Manager'))->toBeTrue();

    $user->removeGlobalRole('Manager');
    expect($user->hasGlobalRole('Manager'))->toBeFalse();
});
