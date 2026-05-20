<?php

use Illuminate\Support\Facades\DB;
use Maxiviper117\Access\Facades\Access;
use Maxiviper117\Access\Support\AccessCache;
use Maxiviper117\Access\Tests\Fixtures\Company;
use Maxiviper117\Access\Tests\Fixtures\Permission;
use Maxiviper117\Access\Tests\Fixtures\User;

beforeEach(function () {
    config()->set('cache.default', 'array');
    config()->set('access.cache.enabled', true);
    config()->set('access.cache.ttl', 3600);
    app(AccessCache::class)->clear();
});

it('caches permission checks on subsequent calls', function () {
    $user = User::query()->create(['email' => 'david@example.com']);
    $company = Company::query()->create(['name' => 'Acme']);

    $user->in($company)->givePermission(Permission::UsersInvite);

    // Warm up the cache
    expect($user->in($company)->can(Permission::UsersInvite))->toBeTrue();

    // Listen to query counts
    $queries = 0;
    DB::listen(function () use (&$queries) {
        $queries++;
    });

    // Check again - should hit cache and not make any DB queries
    expect($user->in($company)->can(Permission::UsersInvite))->toBeTrue();
    expect($queries)->toBe(0);
});

it('invalidates cache when a permission is given or revoked', function () {
    $user = User::query()->create(['email' => 'david@example.com']);
    $company = Company::query()->create(['name' => 'Acme']);

    $user->in($company)->givePermission(Permission::UsersInvite);

    // Warm up the cache
    expect($user->in($company)->can(Permission::UsersInvite))->toBeTrue();

    // Give another permission - should invalidate the cache
    $user->in($company)->givePermission(Permission::RolesManage);

    // Listen to query counts
    $queries = 0;
    DB::listen(function () use (&$queries) {
        $queries++;
    });

    // Should query database since cache is cleared
    expect($user->in($company)->can(Permission::RolesManage))->toBeTrue();
    expect($queries)->toBeGreaterThan(0);

    // Warm up the cache again
    expect($user->in($company)->can(Permission::RolesManage))->toBeTrue();

    // Revoke the permission - should invalidate the cache
    $user->in($company)->revokePermission(Permission::RolesManage);

    $queries = 0;
    expect($user->in($company)->can(Permission::RolesManage))->toBeFalse();
    expect($queries)->toBeGreaterThan(0);
});

it('invalidates cache when a role is assigned or removed', function () {
    Access::role('Editor')->allows([Permission::UsersInvite]);

    $user = User::query()->create(['email' => 'david@example.com']);
    $company = Company::query()->create(['name' => 'Acme']);

    $user->in($company)->assignRole('Editor');

    // Warm up the cache
    expect($user->in($company)->can(Permission::UsersInvite))->toBeTrue();

    // Remove the role - should invalidate the cache
    $user->in($company)->removeRole('Editor');

    // Listen to query counts
    $queries = 0;
    DB::listen(function () use (&$queries) {
        $queries++;
    });

    expect($user->in($company)->can(Permission::UsersInvite))->toBeFalse();
    expect($queries)->toBeGreaterThan(0);
});

it('clears versioned cache when cache clear is executed', function () {
    $user = User::query()->create(['email' => 'david@example.com']);
    $company = Company::query()->create(['name' => 'Acme']);

    $user->in($company)->givePermission(Permission::UsersInvite);

    // Warm up cache
    expect($user->in($company)->can(Permission::UsersInvite))->toBeTrue();

    // Manually clear cache
    app(AccessCache::class)->clear();

    // Listen to queries
    $queries = 0;
    DB::listen(function () use (&$queries) {
        $queries++;
    });

    expect($user->in($company)->can(Permission::UsersInvite))->toBeTrue();
    expect($queries)->toBeGreaterThan(0);
});
