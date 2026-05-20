<?php

use App\Actions\Access\AddPermissionToRole;
use App\Actions\Access\CreateRole;
use App\Actions\Access\DeleteRole;
use App\Actions\Access\RemovePermissionFromRole;
use App\Actions\Access\SyncRolePermissions;
use Maxiviper117\Access\Models\Permission as PermissionModel;
use Maxiviper117\Access\Models\Role;
use Maxiviper117\Access\Tests\Fixtures\Company;
use Maxiviper117\Access\Tests\Fixtures\Permission;
use Maxiviper117\Access\Tests\Fixtures\User;

it('supports creating and resolving dynamic scoped roles', function () {
    $user = User::query()->create(['email' => 'editor@example.com']);
    $companyA = Company::query()->create(['name' => 'Acme Inc']);
    $companyB = Company::query()->create(['name' => 'Beta LLC']);

    // Ensure permissions are in the DB
    $viewPermission = PermissionModel::query()->firstOrCreate(['name' => 'users.view']);
    $invitePermission = PermissionModel::query()->firstOrCreate(['name' => 'users.invite']);

    // Create a dynamic scoped role for Company A
    $roleA = Role::query()->create([
        'name' => 'custom-editor',
        'label' => 'Custom Editor',
        'is_system' => false,
        'scope_type' => $companyA->getMorphClass(),
        'scope_id' => $companyA->getKey(),
    ]);
    $roleA->permissions()->sync([$viewPermission->getKey()]);

    // Create a dynamic scoped role for Company B with a different permission
    $roleB = Role::query()->create([
        'name' => 'custom-editor',
        'label' => 'Custom Editor',
        'is_system' => false,
        'scope_type' => $companyB->getMorphClass(),
        'scope_id' => $companyB->getKey(),
    ]);
    $roleB->permissions()->sync([$invitePermission->getKey()]);

    // Assign the scoped role to the user in Company A and Company B
    $user->in($companyA)->assignRole('custom-editor');
    $user->in($companyB)->assignRole('custom-editor');

    // User in Company A should have users.view but NOT users.invite
    expect($user->in($companyA)->can(Permission::UsersView))->toBeTrue()
        ->and($user->in($companyA)->can(Permission::UsersInvite))->toBeFalse();

    // User in Company B should have users.invite but NOT users.view
    expect($user->in($companyB)->can(Permission::UsersInvite))->toBeTrue()
        ->and($user->in($companyB)->can(Permission::UsersView))->toBeFalse();
});

it('does not prune dynamic roles when running access:sync with prune option', function () {
    // Ensure permissions exist
    PermissionModel::query()->firstOrCreate(['name' => 'users.view']);

    // Create a dynamic scoped role
    $company = Company::query()->create(['name' => 'Acme Inc']);
    $dynamicRole = Role::query()->create([
        'name' => 'custom-designer',
        'label' => 'Custom Designer',
        'is_system' => false,
        'scope_type' => $company->getMorphClass(),
        'scope_id' => $company->getKey(),
    ]);

    // Create a dynamic global role
    $dynamicGlobalRole = Role::query()->create([
        'name' => 'custom-admin',
        'label' => 'Custom Admin',
        'is_system' => false,
        'is_global' => true,
    ]);

    // Configure some system permissions and roles
    config()->set('access.permission_enums', [Permission::class]);
    config()->set('access.roles', [
        'Owner' => [Permission::UsersView],
    ]);
    config()->set('access.global_roles', []);

    // Run sync with --prune --force
    $this->artisan('access:sync --prune --force')->assertSuccessful();

    // The system roles and permissions should be synced
    expect(Role::query()->where('name', 'Owner')->where('is_system', true)->exists())->toBeTrue();

    // The dynamic scoped and global roles should still exist intact!
    expect(Role::query()->where('name', 'custom-designer')->exists())->toBeTrue()
        ->and(Role::query()->where('name', 'custom-admin')->exists())->toBeTrue();
});

it('supports scoped actions on dynamic roles via AccessContext', function () {
    $user = User::query()->create(['email' => 'admin@example.com']);
    $company = Company::query()->create(['name' => 'Acme Inc']);

    // Ensure permissions exist in DB
    PermissionModel::query()->firstOrCreate(['name' => 'users.view']);
    PermissionModel::query()->firstOrCreate(['name' => 'users.invite']);

    // Create a dynamic scoped role via the user context in the company scope
    $role = $user->in($company)->createRole('custom-manager', 'Custom Manager', 'Scoped custom manager');

    expect($role->name)->toBe('custom-manager')
        ->and($role->label)->toBe('Custom Manager')
        ->and($role->description)->toBe('Scoped custom manager')
        ->and($role->is_system)->toBeFalse()
        ->and($role->is_global)->toBeFalse()
        ->and($role->scope_type)->toBe($company->getMorphClass())
        ->and($role->scope_id)->toBe($company->getKey());

    // Sync role permissions
    $user->in($company)->syncRolePermissions('custom-manager', [
        Permission::UsersView,
    ]);

    // Assign dynamic role to user
    $user->in($company)->assignRole('custom-manager');
    expect($user->in($company)->can(Permission::UsersView))->toBeTrue()
        ->and($user->in($company)->can(Permission::UsersInvite))->toBeFalse();

    // Update role permissions dynamically
    $user->in($company)->syncRolePermissions('custom-manager', [
        Permission::UsersView,
        Permission::UsersInvite,
    ]);

    // Check that permissions cache invalidated and permissions synced
    expect($user->in($company)->can(Permission::UsersInvite))->toBeTrue();

    // Listing roles in company scope should return the custom scoped role
    $roles = $user->in($company)->roles();
    expect($roles->pluck('name')->all())->toContain('custom-manager');

    // Deleting a custom role
    $deleteResult = $user->in($company)->deleteRole('custom-manager');
    expect($deleteResult)->toBeTrue();
    expect(Role::query()->where('name', 'custom-manager')->exists())->toBeFalse();

    // Check cache was cleared (should no longer have permission)
    expect($user->in($company)->can(Permission::UsersInvite))->toBeFalse();
});

it('protects system roles from deletion and modification', function () {
    $user = User::query()->create(['email' => 'admin@example.com']);
    $company = Company::query()->create(['name' => 'Acme Inc']);

    // Create a system role
    $systemRole = Role::query()->create([
        'name' => 'super-owner',
        'is_system' => true,
        'is_global' => true,
    ]);

    // Attempting to delete a system role should return false
    $deleteResult = $user->in($company)->deleteRole('super-owner');
    expect($deleteResult)->toBeFalse();
    expect(Role::query()->where('name', 'super-owner')->exists())->toBeTrue();

    // Attempting to modify a system role should throw exception
    expect(function () use ($user, $company) {
        $user->in($company)->syncRolePermissions('super-owner', [Permission::UsersView]);
    })->toThrow(InvalidArgumentException::class, 'Cannot modify system roles.');
});

it('supports adding and removing permissions from dynamic roles via AccessContext', function () {
    $user = User::query()->create(['email' => 'admin@example.com']);
    $company = Company::query()->create(['name' => 'Acme Inc']);

    // Ensure permissions exist in DB
    PermissionModel::query()->firstOrCreate(['name' => 'users.view']);
    PermissionModel::query()->firstOrCreate(['name' => 'users.invite']);

    // Create a dynamic scoped role
    $role = $user->in($company)->createRole('custom-staff', 'Custom Staff', 'Scoped custom staff');

    // Assign dynamic role to user
    $user->in($company)->assignRole('custom-staff');

    // Initially should not have view or invite permission
    expect($user->in($company)->can(Permission::UsersView))->toBeFalse()
        ->and($user->in($company)->can(Permission::UsersInvite))->toBeFalse();

    // Add view permission
    $user->in($company)->addPermissionToRole('custom-staff', Permission::UsersView);
    expect($user->in($company)->can(Permission::UsersView))->toBeTrue()
        ->and($user->in($company)->can(Permission::UsersInvite))->toBeFalse();

    // Add invite permission
    $user->in($company)->addPermissionToRole('custom-staff', Permission::UsersInvite);
    expect($user->in($company)->can(Permission::UsersView))->toBeTrue()
        ->and($user->in($company)->can(Permission::UsersInvite))->toBeTrue();

    // Remove view permission
    $user->in($company)->removePermissionFromRole('custom-staff', Permission::UsersView);
    expect($user->in($company)->can(Permission::UsersView))->toBeFalse()
        ->and($user->in($company)->can(Permission::UsersInvite))->toBeTrue();
});

it('supports executing scaffolded dynamic role actions', function () {
    // Include the stubs dynamically
    include_once __DIR__.'/../../resources/stubs/CreateRole.stub';
    include_once __DIR__.'/../../resources/stubs/DeleteRole.stub';
    include_once __DIR__.'/../../resources/stubs/SyncRolePermissions.stub';
    include_once __DIR__.'/../../resources/stubs/AddPermissionToRole.stub';
    include_once __DIR__.'/../../resources/stubs/RemovePermissionFromRole.stub';

    $company = Company::query()->create(['name' => 'Acme Inc']);
    PermissionModel::query()->firstOrCreate(['name' => 'users.view']);
    PermissionModel::query()->firstOrCreate(['name' => 'users.invite']);

    // Test CreateRole action with Enum
    $roleFromEnum = CreateRole::run(TestRoleEnum::ScopedManager, 'Test Scoped Manager', 'Description', $company);
    expect($roleFromEnum->name)->toBe('test-scoped-manager')
        ->and($roleFromEnum->scope_id)->toBe($company->getKey());

    // Test AddPermissionToRole action using Enum as role reference
    AddPermissionToRole::run(TestRoleEnum::ScopedManager, Permission::UsersView, $company);
    expect($roleFromEnum->permissions()->pluck('name')->all())->toContain('users.view');

    // Test SyncRolePermissions action using Enum as role reference
    SyncRolePermissions::run(TestRoleEnum::ScopedManager, [Permission::UsersView, Permission::UsersInvite], $company);
    expect($roleFromEnum->permissions()->pluck('name')->all())->toContain('users.view', 'users.invite');

    // Test RemovePermissionFromRole action using Enum as role reference
    RemovePermissionFromRole::run(TestRoleEnum::ScopedManager, Permission::UsersView, $company);
    expect($roleFromEnum->permissions()->pluck('name')->all())->not->toContain('users.view')
        ->and($roleFromEnum->permissions()->pluck('name')->all())->toContain('users.invite');

    // Test DeleteRole action using Enum as role reference
    $deletedEnum = DeleteRole::run(TestRoleEnum::ScopedManager, $company);
    expect($deletedEnum)->toBeTrue();
    expect(Role::query()->where('name', 'test-scoped-manager')->exists())->toBeFalse();

    // Test CreateRole action
    $role = CreateRole::run('custom-action-role', 'Custom Action Role', 'Description', $company);
    expect($role->name)->toBe('custom-action-role')
        ->and($role->scope_id)->toBe($company->getKey());

    // Test AddPermissionToRole action
    AddPermissionToRole::run($role, Permission::UsersView, $company);
    expect($role->permissions()->pluck('name')->all())->toContain('users.view');

    // Test SyncRolePermissions action
    SyncRolePermissions::run($role, [Permission::UsersView, Permission::UsersInvite], $company);
    expect($role->permissions()->pluck('name')->all())->toContain('users.view', 'users.invite');

    // Test RemovePermissionFromRole action
    RemovePermissionFromRole::run($role, Permission::UsersView, $company);
    expect($role->permissions()->pluck('name')->all())->not->toContain('users.view')
        ->and($role->permissions()->pluck('name')->all())->toContain('users.invite');

    // Test DeleteRole action
    $deleted = DeleteRole::run($role, $company);
    expect($deleted)->toBeTrue();
    expect(Role::query()->where('name', 'custom-action-role')->exists())->toBeFalse();
});

enum TestRoleEnum: string
{
    case ScopedManager = 'test-scoped-manager';
    case GlobalAdmin = 'test-global-admin';
}

it('supports using backed enums as role names', function () {
    $user = User::query()->create(['email' => 'enum-test@example.com']);
    $company = Company::query()->create(['name' => 'Acme Inc']);

    // Test createRole with BackedEnum
    $role = $user->in($company)->createRole(TestRoleEnum::ScopedManager, 'Scoped Manager', 'Uses enum');
    expect($role->name)->toBe('test-scoped-manager');

    // 1. Test scoped role assignment with BackedEnum
    $user->in($company)->assignRole(TestRoleEnum::ScopedManager);
    expect($user->in($company)->hasRole(TestRoleEnum::ScopedManager))->toBeTrue()
        ->and($user->in($company)->hasRole('test-scoped-manager'))->toBeTrue();

    // Remove scoped role with BackedEnum
    $user->in($company)->removeRole(TestRoleEnum::ScopedManager);
    expect($user->in($company)->hasRole(TestRoleEnum::ScopedManager))->toBeFalse();

    // 2. Test global role assignment with BackedEnum
    $user->assignGlobalRole(TestRoleEnum::GlobalAdmin);
    expect($user->hasGlobalRole(TestRoleEnum::GlobalAdmin))->toBeTrue()
        ->and($user->hasGlobalRole('test-global-admin'))->toBeTrue();

    // Remove global role with BackedEnum
    $user->removeGlobalRole(TestRoleEnum::GlobalAdmin);
    expect($user->hasGlobalRole(TestRoleEnum::GlobalAdmin))->toBeFalse();
});

it('exercises all validation and exception edge cases for scaffolded role actions', function () {
    // Include the stubs dynamically if not already done
    include_once __DIR__.'/../../resources/stubs/CreateRole.stub';
    include_once __DIR__.'/../../resources/stubs/DeleteRole.stub';
    include_once __DIR__.'/../../resources/stubs/SyncRolePermissions.stub';
    include_once __DIR__.'/../../resources/stubs/AddPermissionToRole.stub';
    include_once __DIR__.'/../../resources/stubs/RemovePermissionFromRole.stub';

    $company1 = Company::query()->create(['name' => 'Company One']);
    $company2 = Company::query()->create(['name' => 'Company Two']);

    // Create a dynamic scoped role in company 1
    $role = CreateRole::run('scoped-role', 'Scoped Role', 'Desc', $company1);

    // 1. Non-existent role in AddPermissionToRole should throw InvalidArgumentException
    expect(fn () => AddPermissionToRole::run('non-existent', Permission::UsersView, $company1))
        ->toThrow(InvalidArgumentException::class, 'Role not found.');

    // 2. Non-existent role in RemovePermissionFromRole should throw InvalidArgumentException
    expect(fn () => RemovePermissionFromRole::run('non-existent', Permission::UsersView, $company1))
        ->toThrow(InvalidArgumentException::class, 'Role not found.');

    // 3. Non-existent role in SyncRolePermissions should throw InvalidArgumentException
    expect(fn () => SyncRolePermissions::run('non-existent', [Permission::UsersView], $company1))
        ->toThrow(InvalidArgumentException::class, 'Role not found.');

    // 4. Non-existent role in DeleteRole should return false
    $deleted = DeleteRole::run('non-existent', $company1);
    expect($deleted)->toBeFalse();

    // 5. Scoped role belonging to company 1 accessed with company 2 should throw InvalidArgumentException
    expect(fn () => AddPermissionToRole::run($role, Permission::UsersView, $company2))
        ->toThrow(InvalidArgumentException::class, 'The role does not belong to the given scope.');

    expect(fn () => RemovePermissionFromRole::run($role, Permission::UsersView, $company2))
        ->toThrow(InvalidArgumentException::class, 'The role does not belong to the given scope.');

    expect(fn () => SyncRolePermissions::run($role, [Permission::UsersView], $company2))
        ->toThrow(InvalidArgumentException::class, 'The role does not belong to the given scope.');

    expect(fn () => DeleteRole::run($role, $company2))
        ->toThrow(InvalidArgumentException::class, 'The role does not belong to the given scope.');

    // 6. Scoped role accessed without a scope should throw InvalidArgumentException
    expect(fn () => AddPermissionToRole::run($role, Permission::UsersView, null))
        ->toThrow(InvalidArgumentException::class, 'A scoped role cannot be modified without a scope.');

    expect(fn () => RemovePermissionFromRole::run($role, Permission::UsersView, null))
        ->toThrow(InvalidArgumentException::class, 'A scoped role cannot be modified without a scope.');

    expect(fn () => SyncRolePermissions::run($role, [Permission::UsersView], null))
        ->toThrow(InvalidArgumentException::class, 'A scoped role cannot be modified without a scope.');

    expect(fn () => DeleteRole::run($role, null))
        ->toThrow(InvalidArgumentException::class, 'A scoped role cannot be deleted without a scope.');

    // 7. Global system role should throw exception when trying to modify or delete
    $systemRole = Role::query()->create([
        'name' => 'sys-admin',
        'is_system' => true,
        'is_global' => true,
    ]);

    expect(fn () => AddPermissionToRole::run($systemRole, Permission::UsersView))
        ->toThrow(InvalidArgumentException::class, 'System roles cannot be modified.');

    expect(fn () => RemovePermissionFromRole::run($systemRole, Permission::UsersView))
        ->toThrow(InvalidArgumentException::class, 'System roles cannot be modified.');

    expect(fn () => SyncRolePermissions::run($systemRole, [Permission::UsersView]))
        ->toThrow(InvalidArgumentException::class, 'System roles cannot be modified.');

    expect(fn () => DeleteRole::run($systemRole))
        ->toThrow(InvalidArgumentException::class, 'System roles cannot be deleted.');
});
