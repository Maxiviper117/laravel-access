---
title: Define Permissions
---

# Define Permissions

Use a backed enum as the source of truth for permission names.

Keep permission names stable. They are stored in the database and used by policies, middleware, and frontend permission maps.

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

Point the package at the enum:

```php
'permission_enum' => App\Enums\Permission::class,
```

Sync it:

```bash
php artisan access:sync
```

The sync command creates missing database rows and reports stale permissions before deleting anything.

## Naming Guidelines

Use dot notation:

```php
case UsersInvite = 'users.invite';
case RolesManage = 'roles.manage';
case CompanyUpdate = 'company.update';
```

Prefer verbs that describe capabilities:

- `users.view`
- `users.invite`
- `users.manage`
- `billing.manage`
- `reports.export`

Avoid role names as permission names:

```php
// Avoid
case Owner = 'owner';
case Admin = 'admin';
```

Roles can change shape over time. Permission names should describe the action you are protecting.

## Handling Removed Permissions

Run a dry run first:

```bash
php artisan access:sync --dry-run
```

If a permission is no longer in the enum, the command reports it as stale. Delete stale records only when you are sure nothing needs them:

```bash
php artisan access:sync --prune
```
