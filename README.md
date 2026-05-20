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

Define your permission enum in `app/Enums/Permission.php`, then configure roles in `config/access.php`.

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
    ],
];
```

Sync the enum and configured roles into the database:

```bash
php artisan access:sync
```

## Usage

Assign scoped roles:

```php
$user->in($company)->assignRole('Owner');
$user->in($company)->removeRole('Owner');
```

Check scoped permissions:

```php
$user->in($company)->can(Permission::UsersInvite);
$user->in($company)->cannot(Permission::RolesManage);
```

Use it inside policies:

```php
public function inviteUsers(User $user, Company $company): bool
{
    return $user->in($company)->can(Permission::UsersInvite);
}
```

Build an Inertia-friendly permission map:

```php
Access::for($user)->in($company)->toArray([
    Permission::UsersInvite,
    Permission::RolesManage,
]);
```

## Commands

```bash
php artisan access:install --enum
php artisan access:sync --dry-run
php artisan access:sync --prune
php artisan access:clear
php artisan access:debug user@example.com --scope=company:1
```

## Testing

```bash
composer test
```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
