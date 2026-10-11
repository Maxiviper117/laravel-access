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

The sync command creates missing database rows and reports stale system-owned permissions before deleting anything.

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

## Dynamic Permission Definitions

When app administrators need to create permissions at runtime, define them with a stable string name. Permission definitions are app-wide; a definition can later be assigned directly or attached to a scoped or global role.

```php:line-numbers
use Maxiviper117\Access\Facades\Access;

$permission = Access::definePermission(
    'invoices.export',
    'Export invoices',
    'Download invoice data.',
);
```

This creates or updates a dynamic definition and its label and description. It does not grant the permission to anyone. Grant it separately through the scoped or global assignment APIs, or attach it to a role. Protect the app endpoint that manages definitions and grants with your own authorization policy.

The permission name is a unique, stable key. Renaming it creates a different permission. Dynamic definitions created this way, or by the string-based role and assignment APIs, are retained by `access:sync --prune`. That command only prunes stale definitions marked as system-owned by configuration. If a dynamic name already belongs to a configured permission, `definePermission` throws instead of changing the configured definition.

Revoking a direct assignment or removing a permission from a role only removes that grant; it keeps the definition available for future use. Deleting a dynamic `Permission` model is rejected while it has direct assignments or role attachments. Remove those links first if the app intentionally retires the definition. Use model deletion for this check; bulk query-builder deletes bypass Eloquent model events.

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

If a system-owned permission is no longer in the enum or configured roles, the command reports it as stale. Dynamic permission definitions are not reported or deleted by `--prune`. Delete stale system-owned records only when you are sure nothing needs them:

```bash
php artisan access:sync --prune
```
