<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Maxiviper117\Access\Facades\Access;
use Maxiviper117\Access\Models\Permission;
use Maxiviper117\Access\Models\Role;
use Maxiviper117\Access\Tests\Fixtures\Permission as PermissionEnum;
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
