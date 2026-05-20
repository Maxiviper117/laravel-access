# Laravel Access

Explicit scoped authorization for Laravel — permission enums, polymorphic scopes, and zero implicit state.

Laravel Access gives you **explicit, scoped role-permission authorization** for multi-tenant Laravel applications. Unlike packages that assume one user = one set of permissions, Laravel Access is built for apps where users have **different roles in different scopes** — companies, teams, workspaces, or any Eloquent model.

Permissions are **PHP BackedEnums** (not strings): compile-time safety, IDE autocomplete, single source of truth. The API makes scope **explicit at every call site** — `$user->in($company)->can(Permission::UsersInvite)` — no global state, no `team_id` hacks.

```php
$user->in($company)->can(Permission::UsersInvite);
```

## Installation

```bash
composer require maxiviper117/laravel-access
php artisan access:install --enum
php artisan migrate
php artisan access:sync
```

Add the trait to your user model:

```php
use Maxiviper117\Access\Concerns\HasAccess;

class User extends Authenticatable
{
    use HasAccess;
}
```

## Configuration

Define your permission enum in `app/Enums/Permission.php`, then configure roles and global roles in `config/access.php`.

```php
return [
    'permission_enums' => [
        App\Enums\Permission::class,
    ],

    'roles' => [
        'Owner' => [
            App\Enums\Permission::UsersView,
            App\Enums\Permission::UsersInvite,
        ],
    ],

    'global_roles' => [
        'Platform Admin' => [
            App\Enums\Permission::SystemManage,
        ],
    ],

    'gate_before' => [
        'enabled' => false,
        'global_role' => 'Platform Admin',
    ],
];
```

Sync enums and configured roles into the database:

```bash
php artisan access:sync
```

Or define roles in code with `Access::role()`:

```php
use Maxiviper117\Access\Facades\Access;

Access::role('Owner')->allows([Permission::UsersView, Permission::UsersInvite]);
Access::role('Platform Admin', global: true)->allows([Permission::SystemManage]);
```

## Scoped Usage

Assign and check roles inside a scope:

```php
$user->in($company)->assignRole('Owner');
$user->in($company)->hasRole('Owner');
$user->in($company)->removeRole('Owner');
```

Check permissions resolved through role inheritance and direct grants:

```php
$user->in($company)->can(Permission::UsersInvite);
$user->in($company)->cannot(Permission::RolesManage);
```

Grant or revoke permissions directly (bypassing roles):

```php
$user->in($company)->givePermission(Permission::UsersInvite);
$user->in($company)->revokePermission(Permission::UsersInvite);
```

Check any role in a set:

```php
$user->in($company)->hasAnyRole(['Owner', 'Admin']);
```

Use inside policies:

```php
public function inviteUsers(User $user, Company $company): bool
{
    return $user->in($company)->can(Permission::UsersInvite);
}
```

## Global Usage

Global roles and permissions live outside any scope:

```php
$user->assignGlobalRole('Platform Admin');
$user->hasGlobalRole('Platform Admin');
$user->removeGlobalRole('Platform Admin');
$user->canGlobally(Permission::SystemManage);
$user->giveGlobalPermission(Permission::SystemManage);
```

## Dynamic Roles

Create and manage runtime roles per scope:

```php
$user->in($company)->createRole('custom-manager', 'Custom Manager', 'Description');
$user->in($company)->syncRolePermissions('custom-manager', [Permission::UsersView]);
$user->in($company)->assignRole('custom-manager');
$user->in($company)->deleteRole('custom-manager');
```

## Permission Maps

Build an Inertia-friendly permission map:

```php
Access::for($user)->in($company)->toArray([
    Permission::UsersInvite,
    Permission::RolesManage,
]);
// ['users.invite' => true, 'roles.manage' => false]
```

## Scoped Gates

Register Laravel Gates for all enum cases at once:

```php
Access::defineScopedGates(Permission::class, Company::class);
// Gate::authorize('users.invite', $company);
```

## Middleware

Guard routes with the `access` middleware:

```php
Route::middleware('access:users.invite,company')
    ->get('/companies/{company}/invite', ...);
```

## Commands

```bash
php artisan access:install --enum          # Publish config, migration, Permission enum
php artisan access:sync --dry-run          # Preview changes
php artisan access:sync --prune            # Delete stale permissions and roles
php artisan access:scope --name=company    # Scaffold team/workspace membership
php artisan access:debug user@example.com --scope=company:1
php artisan access:clear                   # Invalidate permission cache
```

## Testing

```bash
composer test
```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
