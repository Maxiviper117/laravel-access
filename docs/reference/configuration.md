---
title: Configuration
---

# Configuration

`config/access.php` controls all package behavior. Below is the **full config** with every option shown.

## Full Config File

```php
<?php

use App\Enums\Permission;
use App\Models\Company;

return [

    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    |
    | The authenticatable model that receives role and permission assignments.
    | Same for both scoped and global-only setups.
    |
    */

    'user_model' => 'App\\Models\\User',

    /*
    |--------------------------------------------------------------------------
    | Permission Enum
    |--------------------------------------------------------------------------
    |
    | A backed enum class used by `access:sync` to validate permissions.
    | Same for both scoped and global-only setups.
    |
    */

    'permission_enum' => Permission::class,

    /*
    |--------------------------------------------------------------------------
    | Default Scope Model
    |--------------------------------------------------------------------------
    |
    | DIFFERENCE BETWEEN MODES:
    |
    |   Scoped mode:     Set to your scope model class (e.g., Company::class)
    |   Global-only:     Leave as null
    |
    | This is used by commands like `debug:access --scope=company:1` to
    | resolve scope strings. It does NOT affect authorization logic itself.
    |
    */

    'default_scope_model' => Company::class, // Scoped mode
    // 'default_scope_model' => null,        // Global-only mode

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | Permission resolution caching. Same for both modes.
    |
    */

    'cache' => [
        'enabled' => env('APP_ENV') !== 'testing',
        'key' => 'access.permissions',
        'ttl' => null, // null = forever until cache clear
    ],

    /*
    |--------------------------------------------------------------------------
    | Scoped Roles
    |--------------------------------------------------------------------------
    |
    | DIFFERENCE BETWEEN MODES:
    |
    |   Scoped mode:     Define roles here. Each assignment is tied to a
    |                    specific scope model instance via in($model).
    |   Global-only:     Leave empty [].
    |
    | Keys are role names. Values are arrays of Permission enum cases.
    |
    */

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

    /*
    |--------------------------------------------------------------------------
    | Global Roles
    |--------------------------------------------------------------------------
    |
    | DIFFERENCE BETWEEN MODES:
    |
    |   Scoped mode:     Use for platform-level roles that apply everywhere
    |                    regardless of scope (e.g., "Platform Admin").
    |   Global-only:     Define ALL your roles here. This is your primary
    |                    role configuration.
    |
    | Keys are role names. Values are arrays of Permission enum cases.
    |
    */

    'global_roles' => [
        'Platform Admin' => [
            Permission::SystemManage,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Gate Before Override
    |--------------------------------------------------------------------------
    |
    | When enabled, users with the specified global role bypass ALL Laravel
    | Gate and Policy checks. Same for both modes.
    |
    | Use with caution — this grants unrestricted access.
    |
    */

    'gate_before' => [
        'enabled' => false,
        'global_role' => 'Platform Admin',
    ],

];
```

## Options Reference

`user_model`
: The authenticatable model that receives assignments. Same for both modes.

`permission_enum`
: A backed enum class used by `access:sync`. Same for both modes.

`default_scope_model`
: **Scoped:** set to your scope model class (e.g., `Company::class`). **Global-only:** leave as `null`. Used by dev commands to resolve scope strings like `company:1`.

`roles`
: **Scoped:** define your per-scope roles here. **Global-only:** leave empty `[]`.

`global_roles`
: **Scoped:** optional platform-level roles that apply everywhere. **Global-only:** define all your roles here.

`cache`
: Permission resolution caching. Same for both modes.

`gate_before`
: Optional Laravel Gate override for a global role. Same for both modes.

## Scoped Mode Quick Reference

```php
'default_scope_model' => Company::class,

'roles' => [
    'Owner' => [Permission::UsersView, Permission::UsersInvite],
],

'global_roles' => [
    'Platform Admin' => [Permission::SystemManage],
],
```

```php
$user->in($company)->assignRole('Owner');
$user->in($company)->can(Permission::UsersInvite);
```

## Global-Only Mode Quick Reference

```php
'default_scope_model' => null,

'roles' => [],

'global_roles' => [
    'Admin' => [Permission::UsersView, Permission::UsersInvite],
    'Viewer' => [Permission::UsersView],
],
```

```php
$user->assignGlobalRole('Admin');
$user->canGlobally(Permission::UsersInvite);
```

## Gate Before Override

When enabled, the configured global role can bypass Laravel Gate checks:

```php
'gate_before' => [
    'enabled' => false, // [!code --]
    'enabled' => true, // [!code ++]
    'global_role' => 'Platform Admin',
],
```

Use this only when platform administrators should be allowed through every Laravel policy and gate check.
