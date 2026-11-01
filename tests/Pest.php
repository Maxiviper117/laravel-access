<?php

declare(strict_types=1);

use Maxiviper117\Access\Models\Role;
use Maxiviper117\Access\Tests\Fixtures\Permission;
use Maxiviper117\Access\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

/** @param array<int, BackedEnum|string> $permissions */
function syncTestRole(string $name, array $permissions, bool $global = false): Role
{
    config()->set('access.permission_enums', [Permission::class]);
    $key = $global ? 'access.global_roles' : 'access.roles';
    $otherKey = $global ? 'access.roles' : 'access.global_roles';
    $roles = config($key, []);
    $otherRoles = config($otherKey, []);
    unset($otherRoles[$name]);
    $roles[$name] = $permissions;
    config()->set($key, $roles);
    config()->set($otherKey, $otherRoles);
    test()->artisan('access:sync')->assertSuccessful();

    return Role::query()->where('name', $name)->whereNull('scope_id')->firstOrFail();
}
