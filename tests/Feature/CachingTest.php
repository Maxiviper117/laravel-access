<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Maxiviper117\Access\Models\Assignment;
use Maxiviper117\Access\Support\AccessCache;
use Maxiviper117\Access\Support\AccessChecker;
use Maxiviper117\Access\Tests\Fixtures\Company;
use Maxiviper117\Access\Tests\Fixtures\Permission;
use Maxiviper117\Access\Tests\Fixtures\User;

beforeEach(function (): void {
    config()->set('cache.default', 'array');
    config()->set('access.cache.enabled', true);
    config()->set('access.cache.ttl', 3600);
    app(AccessCache::class)->clear();
});

it('caches permission checks on subsequent calls', function (): void {
    $user = User::query()->create(['email' => 'david@example.com']);
    $company = Company::query()->create(['name' => 'Acme']);

    $user->in($company)->givePermission(Permission::UsersInvite);

    // Warm up the cache
    expect($user->in($company)->can(Permission::UsersInvite))->toBeTrue();

    // Listen to query counts
    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    // Check again - should hit cache and not make any DB queries
    expect($user->in($company)->can(Permission::UsersInvite))->toBeTrue();
    expect($queries)->toBe(0);
});

it('keeps colon-containing actor and scope identities separate in the permission cache', function (): void {
    $actorA = new class extends Model
    {
        public function getMorphClass(): string
        {
            return 'x:1';
        }
    };
    $actorA->setAttribute($actorA->getKeyName(), 2);

    $actorB = new class extends Model
    {
        public function getMorphClass(): string
        {
            return 'x';
        }
    };
    $actorB->setAttribute($actorB->getKeyName(), 1);

    $scopeA = new class extends Model
    {
        public function getMorphClass(): string
        {
            return 'y';
        }
    };
    $scopeA->setAttribute($scopeA->getKeyName(), 3);

    $scopeB = new class extends Model
    {
        public function getMorphClass(): string
        {
            return '2:y';
        }
    };
    $scopeB->setAttribute($scopeB->getKeyName(), 3);

    $permission = Maxiviper117\Access\Models\Permission::query()->create([
        'name' => Permission::UsersInvite->value,
    ]);

    $assignment = Assignment::query()->create([
        'actor_type' => $actorA->getMorphClass(),
        'actor_id' => $actorA->getKey(),
        'scope_type' => $scopeA->getMorphClass(),
        'scope_id' => $scopeA->getKey(),
        'permission_id' => $permission->getKey(),
    ]);

    $checker = app(AccessChecker::class);

    // The first identity has an assignment. The other tuple serializes to the same old colon key.
    expect($checker->can($actorA, $scopeA, Permission::UsersInvite->value))->toBeTrue()
        ->and($checker->can($actorB, $scopeB, Permission::UsersInvite->value))->toBeFalse();

    $assignment->delete();

    expect($checker->can($actorA, $scopeA, Permission::UsersInvite->value))->toBeFalse()
        ->and($checker->can($actorB, $scopeB, Permission::UsersInvite->value))->toBeFalse();

    Assignment::query()->create([
        'actor_type' => $actorA->getMorphClass(),
        'actor_id' => $actorA->getKey(),
        'permission_id' => $permission->getKey(),
    ]);

    $globalAliasScope = new class extends Model
    {
        public function getMorphClass(): string
        {
            return 'global';
        }
    };
    $globalAliasScope->setAttribute($globalAliasScope->getKeyName(), 'global');

    expect($checker->can($actorA, null, Permission::UsersInvite->value))->toBeTrue()
        ->and($checker->can($actorA, $globalAliasScope, Permission::UsersInvite->value))->toBeFalse();
});

it('invalidates cache when a permission is given or revoked', function (): void {
    $user = User::query()->create(['email' => 'david@example.com']);
    $company = Company::query()->create(['name' => 'Acme']);

    $user->in($company)->givePermission(Permission::UsersInvite);

    // Warm up the cache
    expect($user->in($company)->can(Permission::UsersInvite))->toBeTrue();

    // Give another permission - should invalidate the cache
    $user->in($company)->givePermission(Permission::RolesManage);

    // Listen to query counts
    $queries = 0;
    DB::listen(function () use (&$queries): void {
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

it('invalidates both actor contexts when an assignment changes actor', function (): void {
    $oldActor = User::query()->create(['email' => 'old@example.com']);
    $newActor = User::query()->create(['email' => 'new@example.com']);
    $company = Company::query()->create(['name' => 'Acme']);

    $oldActor->in($company)->givePermission(Permission::UsersInvite);

    expect($oldActor->in($company)->can(Permission::UsersInvite))->toBeTrue()
        ->and($newActor->in($company)->can(Permission::UsersInvite))->toBeFalse();

    $assignment = Assignment::query()->where('actor_id', $oldActor->getKey())->firstOrFail();
    $assignment->actor_type = $newActor->getMorphClass();
    $assignment->actor_id = $newActor->getKey();
    $assignment->save();

    expect($oldActor->in($company)->can(Permission::UsersInvite))->toBeFalse()
        ->and($newActor->in($company)->can(Permission::UsersInvite))->toBeTrue();
});

it('invalidates both scope contexts when an assignment changes scope', function (): void {
    $user = User::query()->create(['email' => 'david@example.com']);
    $oldScope = Company::query()->create(['name' => 'Acme']);
    $newScope = Company::query()->create(['name' => 'Beta']);

    $user->in($oldScope)->givePermission(Permission::UsersInvite);

    expect($user->in($oldScope)->can(Permission::UsersInvite))->toBeTrue()
        ->and($user->in($newScope)->can(Permission::UsersInvite))->toBeFalse();

    $assignment = Assignment::query()->where('actor_id', $user->getKey())->firstOrFail();
    $assignment->scope_type = $newScope->getMorphClass();
    $assignment->scope_id = $newScope->getKey();
    $assignment->save();

    expect($user->in($oldScope)->can(Permission::UsersInvite))->toBeFalse()
        ->and($user->in($newScope)->can(Permission::UsersInvite))->toBeTrue();
});

it('invalidates cache when a role is assigned or removed', function (): void {
    syncTestRole('Editor', [Permission::UsersInvite]);

    $user = User::query()->create(['email' => 'david@example.com']);
    $company = Company::query()->create(['name' => 'Acme']);

    $user->in($company)->assignRole('Editor');

    // Warm up the cache
    expect($user->in($company)->can(Permission::UsersInvite))->toBeTrue();

    // Remove the role - should invalidate the cache
    $user->in($company)->removeRole('Editor');

    // Listen to query counts
    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    expect($user->in($company)->can(Permission::UsersInvite))->toBeFalse();
    expect($queries)->toBeGreaterThan(0);
});

it('invalidates cached permissions for all users when a role changes permissions', function (): void {
    $userA = User::query()->create(['email' => 'alpha@example.com']);
    $userB = User::query()->create(['email' => 'beta@example.com']);
    $company = Company::query()->create(['name' => 'Acme']);

    $userA->in($company)->createRole('Editor');
    $userA->in($company)->syncRolePermissions('Editor', [Permission::UsersInvite]);
    $userA->in($company)->assignRole('Editor');
    $userB->in($company)->assignRole('Editor');

    expect($userB->in($company)->can(Permission::UsersInvite))->toBeTrue();

    $userA->in($company)->addPermissionToRole('Editor', Permission::RolesManage);

    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    expect($userB->in($company)->can(Permission::RolesManage))->toBeTrue();
    expect($queries)->toBeGreaterThan(0);
});

it('clears versioned cache when cache clear is executed', function (): void {
    $user = User::query()->create(['email' => 'david@example.com']);
    $company = Company::query()->create(['name' => 'Acme']);

    $user->in($company)->givePermission(Permission::UsersInvite);

    // Warm up cache
    expect($user->in($company)->can(Permission::UsersInvite))->toBeTrue();

    // Manually clear cache
    app(AccessCache::class)->clear();

    // Listen to queries
    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    expect($user->in($company)->can(Permission::UsersInvite))->toBeTrue();
    expect($queries)->toBeGreaterThan(0);
});
