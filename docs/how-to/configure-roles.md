---
title: Configure Roles
---

# Configure Roles

Define reusable role definitions in `config/access.php`. The published stub has commented examples — replace them with your actual roles:

```php
use App\Enums\Permission;

'roles' => [ // [!code --]
    // 'Owner' => [ // [!code --]
    //     App\Enums\Permission::UsersView, // [!code --]
    // ], // [!code --]
]; // [!code --]
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
