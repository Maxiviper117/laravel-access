---
title: Configuration
---

# Configuration

`config/access.php` controls package behavior.

```php
return [
    'user_model' => 'App\\Models\\User',
    'permission_enum' => App\Enums\Permission::class,
    'default_scope_model' => App\Models\Company::class,

    'cache' => [
        'enabled' => true,
        'key' => 'access.permissions',
        'ttl' => null,
    ],

    'global_roles' => [],
    'roles' => [],

    'gate_before' => [
        'enabled' => false,
        'global_role' => 'Platform Admin',
    ],
];
```

## Options

`user_model`
: The authenticatable model that receives assignments.

`permission_enum`
: A backed enum class used by `access:sync`.

`default_scope_model`
: The model used by commands that resolve scope strings such as `company:1`.

`roles`
: Scoped role definitions.

`global_roles`
: Platform-level role definitions with no scope.

`gate_before`
: Optional Laravel Gate override for a global role.

## Example Role Map

```php
'roles' => [
    'Owner' => [
        Permission::UsersView,
        Permission::UsersInvite,
        Permission::UsersManage,
        Permission::RolesManage,
        Permission::CompanyUpdate,
    ],
    'Admin' => [
        Permission::UsersView,
        Permission::UsersInvite,
        Permission::CompanyUpdate,
    ],
    'Member' => [
        Permission::UsersView,
    ],
],
```

## Gate Before Override

When enabled, the configured global role can bypass Laravel Gate checks:

```php
'gate_before' => [
    'enabled' => true,
    'global_role' => 'Platform Admin',
],
```

Use this only when platform administrators should be allowed through every Laravel policy and gate check.
