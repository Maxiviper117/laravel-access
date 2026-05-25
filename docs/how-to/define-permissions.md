---
title: Define Permissions
---

# Define Permissions

Use backed enums as the source of truth for permission names. Most apps should start with one canonical `App\Enums\Permission` enum.

Keep permission names stable. They are stored in the database and used by policies, middleware, and frontend permission maps.

```php:line-numbers
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

Point the package at the enum by adding it to the `permission_enums` array in `config/access.php`:

```php:line-numbers
'permission_enums' => [
    App\Enums\Permission::class,
],
```

Sync it:

```bash
php artisan access:sync
```

The sync command creates missing database rows and reports stale permissions before deleting anything.

`access:sync` syncs definitions only. It does not assign roles to users. After syncing, use scoped assignments such as `$user->in($company)->assignRole(...)` or global assignments such as `$user->assignGlobalRole(...)` in your application code or seeders.

For modular apps, list more enums:

```php:line-numbers
'permission_enums' => [
    App\Enums\CompanyPermission::class,
    App\Enums\ProjectPermission::class,
    App\Enums\BillingPermission::class,
],
```

Every enum in `permission_enums` is synced.

## Naming Guidelines

Use dot notation:

```php:line-numbers
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

```php:line-numbers
// Avoid
case Owner = 'owner';
case Admin = 'admin';
```

Roles can change shape over time. Permission names should describe the action you are protecting.

For a full first-time and future update workflow, read [Seed roles and permissions](/how-to/seed-roles-and-permissions).

## Handling Removed Permissions

Run a dry run first:

```bash
php artisan access:sync --dry-run
```

If a permission is no longer in the enum, the command reports it as stale. Delete stale records only when you are sure nothing needs them:

```bash
php artisan access:sync --prune
```
