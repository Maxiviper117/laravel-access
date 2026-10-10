<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use Maxiviper117\Access\Models\Assignment;
use Maxiviper117\Access\Models\Permission;
use Maxiviper117\Access\Models\Role;
use Maxiviper117\Access\Tests\Fixtures\User;

it('enforces unique global role names in the database', function (): void {
    Role::query()->create(['name' => 'Platform Admin', 'is_global' => true]);

    expect(fn () => Role::query()->create(['name' => 'Platform Admin', 'is_global' => true]))
        ->toThrow(QueryException::class);
});

it('enforces unique global role assignments in the database', function (): void {
    $user = User::query()->create(['email' => 'david@example.com']);
    $role = Role::query()->create(['name' => 'Platform Admin', 'is_global' => true]);
    $assignment = [
        'actor_type' => $user->getMorphClass(),
        'actor_id' => $user->getKey(),
        'role_id' => $role->getKey(),
    ];

    Assignment::query()->create($assignment);

    expect(fn () => Assignment::query()->create($assignment))
        ->toThrow(QueryException::class);
});

it('enforces unique global direct permission assignments in the database', function (): void {
    $user = User::query()->create(['email' => 'david@example.com']);
    $permission = Permission::query()->create(['name' => 'system.manage']);
    $assignment = [
        'actor_type' => $user->getMorphClass(),
        'actor_id' => $user->getKey(),
        'permission_id' => $permission->getKey(),
    ];

    Assignment::query()->create($assignment);

    expect(fn () => Assignment::query()->create($assignment))
        ->toThrow(QueryException::class);
});

it('can roll back and reapply global uniqueness constraints', function (): void {
    $migration = include __DIR__.'/../../database/migrations/add_global_access_uniqueness.php.stub';

    $migration->down();

    expect(Schema::hasColumn('access_roles', 'global_name'))->toBeFalse()
        ->and(Schema::hasColumn('access_assignments', 'global_scope_key'))->toBeFalse();

    $migration->up();

    expect(Schema::hasColumn('access_roles', 'global_name'))->toBeTrue()
        ->and(Schema::hasColumn('access_assignments', 'global_scope_key'))->toBeTrue();
});
