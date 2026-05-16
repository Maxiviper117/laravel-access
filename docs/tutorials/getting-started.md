---
title: Getting Started
---

# Getting Started

This tutorial takes a new Laravel app from install to a scoped permission check.

The example assumes your app has a `Company` model and users belong to companies through your own application logic. Laravel Access does not manage membership records; it only stores access assignments.

## Install

```bash
composer require maxiviper117/laravel-access
php artisan access:install --enum
php artisan migrate
```

`access:install --enum` publishes config and migrations, then creates a starter enum at `app/Enums/Permission.php` when the file does not already exist.

Add the trait to your user model:

```php
use Maxiviper117\Access\Concerns\HasAccess;

class User extends Authenticatable
{
    use HasAccess;
}
```

The trait adds the fluent `in($scope)` method and global role helpers.

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

## Configure Roles

Edit `config/access.php`:

```php
use App\Enums\Permission;
use App\Models\Company;

return [
    'permission_enum' => Permission::class,
    'default_scope_model' => Company::class,

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

    'global_roles' => [
        'Platform Admin' => [
            Permission::SystemManage,
        ],
    ],
];
```

Sync the database:

```bash
php artisan access:sync
```

Use `--dry-run` when you want to preview changes before writing them:

```bash
php artisan access:sync --dry-run
```

## Assign a Role

```php
$user->in($company)->assignRole('Owner');
```

That assignment applies only to that company. The same user can have a different role in another company:

```php
$user->in($otherCompany)->assignRole('Member');
```

## Check a Permission

```php
$user->in($company)->can(Permission::UsersInvite);
```

Raw strings also work, but enums are preferred:

```php
$user->in($company)->can('users.invite');
```

## Use a Policy

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

## Share Permissions With Inertia

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

Next, read [the mental model](/explanation/mental-model) or jump to [configuration reference](/reference/configuration).
