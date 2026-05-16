---
title: Configure Roles
---

# Configure Roles

Define reusable role definitions in `config/access.php`.

Roles are bundles of permissions. Assign roles to users in a scope; check permissions in application code.

```php
use App\Enums\Permission;

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

Global roles are separate:

```php
'global_roles' => [
    'Platform Admin' => [
        Permission::SystemManage,
    ],
],
```

Global roles are not attached to a company or team. Use them for platform-level administration.

Preview changes:

```bash
php artisan access:sync --dry-run
```

Apply changes:

```bash
php artisan access:sync
```

## Assign Roles

```php
$user->in($company)->assignRole('Owner');
$user->in($company)->assignRole('Member');
```

Role names are reusable definitions. A user can be an `Owner` in one company and a `Member` in another:

```php
$user->in($companyA)->assignRole('Owner');
$user->in($companyB)->assignRole('Member');
```

## Update Role Permissions

Change the config, then run sync again:

```bash
php artisan access:sync
```

The sync command updates the role-permission pivot rows to match config.
