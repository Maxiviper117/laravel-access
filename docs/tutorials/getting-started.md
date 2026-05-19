---
title: Getting Started
---

# Getting Started

This tutorial takes a new Laravel app from install to a permission check.

Laravel Access supports two modes:

- **Scoped** — permissions are isolated per model (Company, Team, Workspace, etc.). The same user can have different roles in different scopes. This is the primary design.
- **Global-only** — permissions apply everywhere. No scope model is needed. Use this for single-tenant apps or platform-level admin roles.

Pick the mode that fits your app and follow the relevant path.

## Install

```bash
composer require maxiviper117/laravel-access
php artisan access:install --enum
php artisan migrate
```

`access:install --enum` publishes config and migrations, creates a starter enum at `app/Enums/Permission.php` when the file does not already exist, and adds it to `permission_enums` in `config/access.php`.

Add the trait to your user model:

```php
use Maxiviper117\Access\Concerns\HasAccess;

class User extends Authenticatable
{
    use HasAccess;
}
```

The trait adds the fluent `in($scope)` method for scoped usage and global role helpers for global-only usage.

## Define Permissions

Edit `app/Enums/Permission.php`:

```php
namespace App\Enums;

enum Permission: string
{
    case UsersView = 'users.view';
    case UsersInvite = 'users.invite';
    case UsersManage = 'users.manage';
    case RolesManage = 'roles.manage';
    case CompanyUpdate = 'company.update';
    case SystemManage = 'system.manage';
}
```

Permission names should describe abilities, not roles. Use `users.invite` instead of `owner` or `admin`.

---

## Path A: Scoped Setup

Use this when your app has multiple organizations, teams, or tenants and a user's permissions differ depending on which one they're acting in.

### Configure Roles

Edit `config/access.php`. Lines with a red `-` are removed from the stub. Lines with a green `+` are what you write:

```php
use App\Enums\Permission;
use App\Models\Company;

return [
    'user_model' => 'App\\Models\\User',

    'permission_enums' => [], // [!code --]
    'permission_enums' => [Permission::class], // [!code ++]

    'default_scope_model' => null, // [!code --]
    'default_scope_model' => Company::class, // [!code ++]

    'cache' => [
        'enabled' => env('APP_ENV') !== 'testing',
        'key' => 'access.permissions',
        'ttl' => null,
    ],

    'global_roles' => [ // [!code --]
        // 'Platform Admin' => [ // [!code --]
        //     App\Enums\Permission::SystemManage, // [!code --]
        // ], // [!code --]
    ], // [!code --]
    'global_roles' => [ // [!code ++]
        'Platform Admin' => [ // [!code ++]
            Permission::SystemManage, // [!code ++]
        ], // [!code ++]
    ], // [!code ++]

    'roles' => [ // [!code --]
        // 'Owner' => [ // [!code --]
        //     App\Enums\Permission::UsersView, // [!code --]
        // ], // [!code --]
    ], // [!code --]
    'roles' => [ // [!code ++]
        'Owner' => [ // [!code ++]
            Permission::UsersView, // [!code ++]
            Permission::UsersInvite, // [!code ++]
            Permission::UsersManage, // [!code ++]
            Permission::RolesManage, // [!code ++]
            Permission::CompanyUpdate, // [!code ++]
        ], // [!code ++]
        'Admin' => [ // [!code ++]
            Permission::UsersView, // [!code ++]
            Permission::UsersInvite, // [!code ++]
            Permission::CompanyUpdate, // [!code ++]
        ], // [!code ++]
        'Member' => [ // [!code ++]
            Permission::UsersView, // [!code ++]
        ], // [!code ++]
    ], // [!code ++]

    'gate_before' => [
        'enabled' => false,
        'global_role' => 'Platform Admin',
    ],
];
```

Sync the database:

```bash
php artisan access:sync
```

`access:sync` creates permissions and roles in the database based on config definitions.

Use `--dry-run` when you want to preview changes before writing them:

```bash
php artisan access:sync --dry-run
```

`access:sync` creates permissions and roles in the database; `--dry-run` previews what would be created.

### Assign a Role

```php
$user->in($company)->assignRole('Owner');
```

That assignment applies only to that company. The same user can have a different role in another company:

```php
$user->in($otherCompany)->assignRole('Member');
```

### Check a Permission

```php
$user->in($company)->can(Permission::UsersInvite);
```

Raw strings also work, but enums are preferred:

```php
$user->in($company)->can('users.invite');
```

### Use a Policy

```php
public function inviteUsers(User $user, Company $company): bool
{
    return $user->in($company)->can(Permission::UsersInvite);
}
```

Controllers can keep using Laravel authorization:

```php
$this->authorize('inviteUsers', $company);
```

### Share Permissions With Inertia

In your Inertia middleware, share only the permissions the current page needs:

```php
use App\Enums\Permission;
use Maxiviper117\Access\Facades\Access;

'access' => fn () => $request->user() && $company
    ? Access::for($request->user())->in($company)->toArray([
        Permission::UsersInvite,
        Permission::RolesManage,
        Permission::CompanyUpdate,
    ])
    : [],
```

The frontend receives:

```php
[
    'users.invite' => true,
    'roles.manage' => false,
    'company.update' => true,
]
```

Frontend checks should hide or show UI. Backend policies still protect the action.

---

## Path B: Global-Only Setup

Use this when your app has no multi-tenant isolation — permissions apply everywhere for a given user.

### Configure Roles

Edit `config/access.php`. Lines with a red `-` are removed from the stub. Lines with a green `+` are what you write:

```php
use App\Enums\Permission;

return [
    'user_model' => 'App\\Models\\User',

    'permission_enums' => [], // [!code --]
    'permission_enums' => [Permission::class], // [!code ++]

    'default_scope_model' => null,

    'cache' => [
        'enabled' => env('APP_ENV') !== 'testing',
        'key' => 'access.permissions',
        'ttl' => null,
    ],

    'global_roles' => [ // [!code --]
        // 'Platform Admin' => [ // [!code --]
        //     App\Enums\Permission::SystemManage, // [!code --]
        // ], // [!code --]
    ], // [!code --]
    'global_roles' => [ // [!code ++]
        'Admin' => [ // [!code ++]
            Permission::UsersView, // [!code ++]
            Permission::UsersInvite, // [!code ++]
            Permission::UsersManage, // [!code ++]
            Permission::RolesManage, // [!code ++]
            Permission::CompanyUpdate, // [!code ++]
        ], // [!code ++]
        'Manager' => [ // [!code ++]
            Permission::UsersView, // [!code ++]
            Permission::UsersInvite, // [!code ++]
            Permission::CompanyUpdate, // [!code ++]
        ], // [!code ++]
        'Viewer' => [ // [!code ++]
            Permission::UsersView, // [!code ++]
        ], // [!code ++]
    ], // [!code ++]

    'roles' => [ // [!code --]
        // 'Owner' => [ // [!code --]
        //     App\Enums\Permission::UsersView, // [!code --]
        // ], // [!code --]
    ], // [!code --]
    'roles' => [], // [!code ++]

    'gate_before' => [
        'enabled' => false,
        'global_role' => 'Platform Admin',
    ],
];
```

Sync the database:

```bash
php artisan access:sync
```

`access:sync` creates permissions and roles in the database based on config definitions.

### Assign a Role

```php
$user->assignGlobalRole('Admin');
```

Or use the `access()` context directly:

```php
$user->access()->assignRole('Admin');
```

### Check a Permission

```php
$user->canGlobally(Permission::UsersInvite);
```

Or via the context:

```php
$user->access()->can(Permission::UsersInvite);
```

### Use a Policy

```php
public function inviteUsers(User $user): bool
{
    return $user->canGlobally(Permission::UsersInvite);
}
```

### Share Permissions With Inertia

```php
use App\Enums\Permission;
use Maxiviper117\Access\Facades\Access;

'access' => fn () => $request->user()
    ? Access::for($request->user())->toArray([
        Permission::UsersInvite,
        Permission::RolesManage,
        Permission::CompanyUpdate,
    ])
    : [],
```

### What You Lose Without Scopes

| Feature | Scoped | Global-only |
|---------|--------|-------------|
| Multi-tenant isolation | Yes | No |
| Same user, different roles per context | Yes | No |
| Route middleware (`access:perm,model`) | Yes | No |
| `defineScopedGates()` | Yes | No |
| Global role helpers | Yes | Yes |
| `access()->can()` | Yes | Yes |

The middleware and `defineScopedGates()` require a scope model to resolve. For global-only apps, use policies or direct `$user->canGlobally()` checks instead.

---

Next, read [the mental model](/explanation/mental-model) or jump to [configuration reference](/reference/configuration).
