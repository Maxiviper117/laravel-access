---
title: User API
---

# User API

Add `HasAccess` to the user model.

```php
use Maxiviper117\Access\Concerns\HasAccess;

class User extends Authenticatable
{
    use HasAccess;
}
```

## Scoped API

```php
$user->in($company)->assignRole('Owner');
$user->in($company)->removeRole('Owner');
$user->in($company)->hasRole('Owner');
$user->in($company)->hasAnyRole(['Owner', 'Admin']);

$user->in($company)->can(Permission::UsersInvite);
$user->in($company)->cannot(Permission::RolesManage);
$user->in($company)->hasPermission(Permission::CompanyUpdate);

$user->in($company)->givePermission(Permission::UsersInvite);
$user->in($company)->revokePermission(Permission::UsersInvite);
```

### `in(Model $scope)`

Returns an access context for the given scope.

```php
$context = $user->in($company);
```

All scoped checks and assignments are made through that context.

### Role Methods

`assignRole(BackedEnum|string|Role $role)`
: Assigns a role in the current scope.

`removeRole(BackedEnum|string|Role $role)`
: Removes a role assignment from the current scope.

`hasRole(BackedEnum|string|Role $role)`
: Checks whether the actor has a role in the current scope.

`hasAnyRole(array $roles)`
: Checks whether any listed role is assigned in the current scope. The array can contain `BackedEnum`, `string`, or `Role` instances.

Role checks are available, but permission checks are preferred in policies.

### Permission Methods

`can(BackedEnum|string $permission)`
: Checks direct and role-derived permissions in the current scope.

`cannot(BackedEnum|string $permission)`
: Negates `can(...)`.

`hasPermission(BackedEnum|string $permission)`
: Alias for `can(...)`.

`givePermission(BackedEnum|string $permission)`
: Assigns a direct scoped permission.

`revokePermission(BackedEnum|string $permission)`
: Removes a direct scoped permission.

## Global API

```php
$user->assignGlobalRole('Platform Admin');
$user->removeGlobalRole('Platform Admin');
$user->hasGlobalRole('Platform Admin');

$user->canGlobally(Permission::SystemManage);
$user->giveGlobalPermission(Permission::SystemManage);
```

Global role assignments have no scope. They are stored with null `scope_type` and `scope_id`.

### Global Role Methods

`assignGlobalRole(BackedEnum|string|Role $role)`
: Assigns an app-wide global role.

`removeGlobalRole(BackedEnum|string|Role $role)`
: Removes an app-wide global role.

`hasGlobalRole(BackedEnum|string|Role $role)`
: Checks whether the actor has an app-wide global role.

`canGlobally(BackedEnum|string $permission)`
: Checks direct and role-derived permissions globally (outside any scope).

`giveGlobalPermission(BackedEnum|string $permission)`
: Assigns a direct global permission.

`revokeGlobalPermission(BackedEnum|string $permission)`
: Removes a direct global permission.

## Permission Maps

```php
$user->in($company)->toArray([
    Permission::UsersInvite,
    Permission::RolesManage,
]);
```

Output:

```php
[
    'users.invite' => true,
    'roles.manage' => false,
]
```
