<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Maxiviper117\Access\Data\PermissionMetadata;
use Maxiviper117\Access\Facades\Access;
use Maxiviper117\Access\Models\Permission;
use Maxiviper117\Access\Models\Role;
use Maxiviper117\Access\Tests\Fixtures\Permission as PermissionEnum;
use Maxiviper117\Access\Tests\Fixtures\StructuredPermission;
use Maxiviper117\Access\Tests\Fixtures\User;

it('defines dynamic permissions without granting them and allows metadata updates', function (): void {
    $permission = Access::definePermission(
        'invoices.export',
        'Export invoices',
        'Download invoice data.',
    );
    $user = User::query()->create(['email' => 'viewer@example.com']);

    expect($permission->name)->toBe('invoices.export')
        ->and($permission->label)->toBe('Export invoices')
        ->and($permission->description)->toBe('Download invoice data.')
        ->and($permission->is_system)->toBeFalse()
        ->and($user->canGlobally('invoices.export'))->toBeFalse();

    $updated = Access::definePermission('invoices.export', 'Download invoices');

    expect($updated->getKey())->toBe($permission->getKey())
        ->and($updated->label)->toBe('Download invoices')
        ->and($updated->description)->toBeNull();
});

it('stores and clears structured metadata on dynamic permission definitions', function (): void {
    $permission = Access::definePermission(
        'invoices.fields.internal_cost.view',
        'View invoice cost',
        metadata: PermissionMetadata::field('invoices', 'internal_cost', 'view'),
    );

    expect($permission->resource)->toBe('invoices')
        ->and($permission->kind)->toBe('field')
        ->and($permission->target)->toBe('internal_cost')
        ->and($permission->ability)->toBe('view');

    $updated = Access::definePermission('invoices.fields.internal_cost.view', 'View invoice cost');

    expect($updated->resource)->toBeNull()
        ->and($updated->kind)->toBeNull()
        ->and($updated->target)->toBeNull()
        ->and($updated->ability)->toBeNull();
});

it('writes enum metadata when registering a code-defined role', function (): void {
    Access::role('Invoice Analyst')->allows([StructuredPermission::InternalCostView]);

    $permission = Permission::query()->where('name', StructuredPermission::InternalCostView->value)->firstOrFail();

    expect($permission->is_system)->toBeTrue()
        ->and($permission->resource)->toBe('invoices')
        ->and($permission->kind)->toBe('field')
        ->and($permission->target)->toBe('internal_cost')
        ->and($permission->ability)->toBe('view');
});

it('preserves permission metadata when a role references its name', function (): void {
    $permission = Access::definePermission(
        'invoices.export',
        metadata: PermissionMetadata::resource('invoices', 'export'),
    );

    Access::role('Invoice Analyst')->allows(['invoices.export']);

    expect($permission->fresh()->resource)->toBe('invoices')
        ->and($permission->fresh()->kind)->toBe('resource')
        ->and($permission->fresh()->ability)->toBe('export');
});

it('preserves synced metadata when a role references its name', function (): void {
    config()->set('access.permission_enums', [StructuredPermission::class]);
    $this->artisan('access:sync')->assertSuccessful();

    Access::role('Invoice Analyst')->allows(['invoices.view']);

    $permission = Permission::query()->where('name', 'invoices.view')->firstOrFail();
    expect($permission->resource)->toBe('invoices')
        ->and($permission->kind)->toBe('resource')
        ->and($permission->ability)->toBe('view');
});

it('preserves metadata when a role references an enum without metadata', function (): void {
    $permission = Access::definePermission('users.view', metadata: PermissionMetadata::resource('users', 'view'));

    Access::role('Viewer')->allows([PermissionEnum::UsersView]);

    expect($permission->fresh()->resource)->toBe('users')
        ->and($permission->fresh()->ability)->toBe('view');
});

it('rejects empty metadata identifiers', function (): void {
    expect(fn () => PermissionMetadata::field('invoices', ' ', 'view'))
        ->toThrow(InvalidArgumentException::class, 'Permission metadata target cannot be empty.');
});

it('rejects metadata identifiers that exceed the database column length', function (): void {
    expect(fn () => PermissionMetadata::resource(str_repeat('a', 256), 'view'))
        ->toThrow(InvalidArgumentException::class, 'Permission metadata resource cannot exceed 255 characters.');
});

it('preserves preexisting permission rows as dynamic during the ownership migration', function (): void {
    $permission = Permission::query()->create(['name' => 'legacy.export']);

    Schema::table('access_permissions', function (Blueprint $table): void {
        $table->dropColumn('is_system');
    });

    (include __DIR__.'/../../database/migrations/add_permission_ownership.php.stub')->up();

    expect($permission->fresh()->is_system)->toBeFalse();
});

it('does not allow a dynamic definition to take ownership of a configured permission', function (): void {
    Access::role('Viewer')->allows([PermissionEnum::UsersView]);

    expect(fn () => Access::definePermission('users.view', 'View users'))
        ->toThrow(InvalidArgumentException::class, 'defined by application configuration');

    expect(Permission::query()->where('name', 'users.view')->first()->is_system)->toBeTrue();
});

it('keeps system ownership out of permission mass assignment', function (): void {
    $permission = Permission::query()->create([
        'name' => 'reports.view',
        'is_system' => true,
    ]);

    expect($permission->fresh()->is_system)->toBeFalse();
});

it('prevents deleting a dynamic permission while it is in use', function (): void {
    $permission = Access::definePermission('invoices.approve', 'Approve invoices');
    $user = User::query()->create(['email' => 'approver@example.com']);
    $user->giveGlobalPermission('invoices.approve');

    expect(fn () => $permission->delete())
        ->toThrow(InvalidArgumentException::class, 'Cannot delete a dynamic permission');

    $user->revokeGlobalPermission('invoices.approve');
    $role = Role::query()->create([
        'name' => 'invoice-approver',
        'is_system' => false,
        'is_global' => true,
    ]);
    $role->permissions()->attach($permission->getKey());

    expect(fn () => $permission->delete())
        ->toThrow(InvalidArgumentException::class, 'Cannot delete a dynamic permission');

    $role->permissions()->detach($permission->getKey());

    expect($permission->delete())->toBeTrue()
        ->and(Permission::query()->where('name', 'invoices.approve')->exists())->toBeFalse();
});
