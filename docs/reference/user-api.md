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

`assignRole(string|Role $role)`
: Assigns a role in the current scope.

`removeRole(string|Role $role)`
: Removes a role assignment from the current scope.

`hasRole(string|Role $role)`
: Checks whether the actor has a role in the current scope.

`hasAnyRole(array $roles)`
: Checks whether any listed role is assigned in the current scope.

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
