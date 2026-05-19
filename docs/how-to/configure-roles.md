---
title: Configure Roles
---

# Configure Roles

Define reusable role definitions in `config/access.php`. The published stub has commented examples — replace them with your actual roles:

There are two role buckets:

- `roles` are scoped roles. Assign them with `$user->in($scope)->assignRole(...)`.
- `global_roles` are app-wide roles. Assign them with `$user->assignGlobalRole(...)`.

For global-only apps, leave `roles` empty and put your app roles in `global_roles`. For company, team, workspace, or tenant apps, put per-scope roles in `roles` and reserve `global_roles` for platform-level access.

## Choose Permission Enums

Laravel Access syncs permission enums listed in `permission_enums`.

Use the app-wide `Permission` enum as the default source of truth:

```php
// config/access.php
use App\Enums\CompanyRole;
use App\Enums\Permission;

'permission_enums' => [
    Permission::class,
],

'roles' => [
    CompanyRole::Owner->value => [
        Permission::CompanyMembersInvite,
        Permission::CompanySettingsManage,
        Permission::BillingManage,
    ],
],
```

`access:install --enum` creates `app/Enums/Permission.php` and configures it here. `access:scope --name=company` adds company starter cases to that enum when the file exists:

```php
enum Permission: string
{
    case CompanyMembersView = 'company.members.view';
    case CompanyMembersInvite = 'company.members.invite';
    case CompanyMembersManage = 'company.members.manage';
    case CompanySettingsManage = 'company.settings.manage';

    case ProjectsCreate = 'projects.create';
    case BillingManage = 'billing.manage';
}
```

This is the recommended default. One enum can hold permissions across company membership, projects, billing, reports, and platform administration.

Use multiple permission enums only when you deliberately want module-owned permission files:

```php
// config/access.php
use App\Enums\BillingPermission;
use App\Enums\CompanyPermission;
use App\Enums\CompanyRole;
use App\Enums\ProjectPermission;

'permission_enums' => [
    CompanyPermission::class,
    ProjectPermission::class,
    BillingPermission::class,
],

'roles' => [
    CompanyRole::Owner->value => [
        CompanyPermission::MembersView,
        CompanyPermission::MembersInvite,
        CompanyPermission::MembersManage,
        CompanyPermission::SettingsManage,
    ],
],
```

In this modular setup, every enum listed in `permission_enums` is synced by `access:sync`. Role definitions may reference cases from any listed enum.

The role keys and permission values are separate choices:

- **Config file keys**: Because PHP array keys must be strings or integers, role keys in `config/access.php` must still use `CompanyRole::Owner->value`, `CompanyRole::Admin->value`, or `CompanyRole::Member->value`.
- **API Methods**: When assigning, checking, or removing roles, you can pass BackedEnum instances directly (e.g., `CompanyRole::Owner`), as Laravel Access natively resolves backed enums.
- **Permission values**: Permission values should come from one of the enums configured in `permission_enums`.

## Scoped Roles

```php
use App\Enums\CompanyRole;
use App\Enums\Permission;

'roles' => [ // [!code --]
    // 'Owner' => [ // [!code --]
    //     App\Enums\Permission::CompanyMembersView, // [!code --]
    // ], // [!code --]
]; // [!code --]
'roles' => [ // [!code ++]
    CompanyRole::Owner->value => [ // [!code ++]
        Permission::CompanyMembersView, // [!code ++]
        Permission::CompanyMembersInvite, // [!code ++]
        Permission::CompanyMembersManage, // [!code ++]
        Permission::RolesManage, // [!code ++]
        Permission::CompanyUpdate, // [!code ++]
    ], // [!code ++]
    CompanyRole::Admin->value => [ // [!code ++]
        Permission::CompanyMembersView, // [!code ++]
        Permission::CompanyMembersInvite, // [!code ++]
        Permission::CompanyUpdate, // [!code ++]
    ], // [!code ++]
    CompanyRole::Member->value => [ // [!code ++]
        Permission::CompanyMembersView, // [!code ++]
    ], // [!code ++]
], // [!code ++]
```

Global roles are separate. Replace the stub's commented block:

```php
'global_roles' => [ // [!code --]
    // 'Platform Admin' => [ // [!code --]
    //     App\Enums\Permission::SystemManage, // [!code --]
    // ], // [!code --]
]; // [!code --]
'global_roles' => [ // [!code ++]
    'Platform Admin' => [ // [!code ++]
        Permission::SystemManage, // [!code ++]
    ], // [!code ++]
], // [!code ++]
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
$user->in($company)->assignRole(CompanyRole::Owner);
$user->in($company)->assignRole(CompanyRole::Member);
```

> [!TIP]
> Laravel Access natively supports PHP Backed Enums for all role methods. You can pass the enum instance directly (e.g., `CompanyRole::Owner`) without having to append `->value` when assigning, removing, or checking roles.

Role names are reusable definitions. A user can be an `Owner` in one company and a `Member` in another:

```php
$user->in($companyA)->assignRole(CompanyRole::Owner);
$user->in($companyB)->assignRole(CompanyRole::Member);
```

## Update Role Permissions

Change the config, then run sync again:

```bash
php artisan access:sync
```

The sync command updates the role-permission pivot rows to match config.

For first-time and future seeding workflows, read [Seed roles and permissions](/how-to/seed-roles-and-permissions).
