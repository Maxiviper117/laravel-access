---
title: Seed Roles and Permissions
---

# Seed Roles and Permissions

Use seeders for two different jobs:

1. Sync the role and permission definitions from `config/access.php` into the `access_*` tables.
2. Assign those roles or direct permissions to real users, either globally or inside a scope.

Keep those jobs separate. `access:sync` creates and updates definitions. Your seeders decide which users receive those definitions.

## First-Time Setup

For a scoped app, install the package, scaffold your scope, migrate, then sync:

```bash
php artisan access:install --enum
php artisan access:scope --name=company
php artisan migrate
php artisan access:sync
```

For a global-only app, skip the scope scaffold:

```bash
php artisan access:install --enum
php artisan migrate
php artisan access:sync
```

Use `--dry-run` before changing an existing database:

```bash
php artisan access:sync --dry-run
php artisan access:sync
```

## Definitions vs Assignments

Definitions live in code:

```php
// app/Enums/Permission.php
enum Permission: string
{
    case CompanyMembersView = 'company.members.view';
    case CompanyMembersInvite = 'company.members.invite';
    case BillingManage = 'billing.manage';
}
```

```php
// config/access.php
'permission_enums' => [
    Permission::class,
],

'roles' => [
    CompanyRole::Owner->value => [
        Permission::CompanyMembersView,
        Permission::CompanyMembersInvite,
        Permission::BillingManage,
    ],
],

'global_roles' => [
    'Platform Admin' => [
        Permission::BillingManage,
    ],
],
```

Assignments live in the database:

```php
$user->in($company)->assignRole(CompanyRole::Owner->value);
$user->assignGlobalRole('Platform Admin');
```

When you change permissions or role definitions, update the enum/config and run `access:sync`. When you change who should have access, update your app logic or assignment seeders.

## Scoped Apps

In scoped apps, `roles` are assigned inside a model such as a company, team, workspace, or tenant. The same user can have different roles in different scopes.

```php
// config/access.php
'default_scope_model' => Company::class,

'roles' => [
    CompanyRole::Owner->value => [
        Permission::CompanyMembersView,
        Permission::CompanyMembersInvite,
        Permission::BillingManage,
    ],
    CompanyRole::Member->value => [
        Permission::CompanyMembersView,
    ],
],

'global_roles' => [
    'Platform Admin' => [
        Permission::BillingManage,
    ],
],
```

Seed both app membership and Laravel Access assignment when using the `access:scope` scaffold:

```php
namespace Database\Seeders;

use App\Enums\CompanyRole;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoCompanySeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::query()->firstOrCreate(
            ['email' => 'owner@example.com'],
            ['name' => 'Owner', 'password' => bcrypt('password')]
        );

        $company = Company::query()->firstOrCreate(
            ['slug' => 'acme-corp'],
            ['name' => 'Acme Corp']
        );

        $company->users()->syncWithoutDetaching([
            $owner->getKey() => ['role' => CompanyRole::Owner->value],
        ]);

        $owner->in($company)->assignRole(CompanyRole::Owner->value);
        $owner->switchCompany($company);
    }
}
```

The membership write and access assignment are intentionally separate:

```php
$company->users()->syncWithoutDetaching([
    $owner->getKey() => ['role' => CompanyRole::Owner->value],
]);

$owner->in($company)->assignRole(CompanyRole::Owner->value);
```

The first line says the user belongs to the company. The second line says what the user may do in that company.

## Global-Only Apps

In global-only apps, leave `default_scope_model` as `null`, leave `roles` empty, and put app-wide roles in `global_roles`.

```php
// config/access.php
'default_scope_model' => null,

'roles' => [],

'global_roles' => [
    'Admin' => [
        Permission::UsersView,
        Permission::UsersInvite,
        Permission::BillingManage,
    ],
    'Viewer' => [
        Permission::UsersView,
    ],
],
```

Then seed global assignments:

```php
namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DemoAdminSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->firstOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'Admin', 'password' => bcrypt('password')]
        );

        $admin->assignGlobalRole('Admin');
    }
}
```

Global roles have no `scope_type` or `scope_id`. Use them for single-tenant apps or platform-wide administration, not for company membership.

## Future Updates

Use this workflow when roles or permissions change after launch:

1. Add or rename permission enum cases carefully. Permission values are stored in the database.
2. Update `roles` or `global_roles` in `config/access.php`.
3. Preview the change with `php artisan access:sync --dry-run`.
4. Run `php artisan access:sync`.
5. Deploy code and config together.
6. Run assignment seeders only when specific users or scopes need new assignments.

When removing permissions, prune only after you confirm the old permission is no longer used:

```bash
php artisan access:sync --dry-run
php artisan access:sync --prune
```

`--prune` deletes stale definitions and related role-permission rows. It does not decide which users should receive replacement roles.

## Idempotent Seeders

Seeders should be safe to run more than once:

- Use `firstOrCreate` or `updateOrCreate` for users and scopes.
- Use `syncWithoutDetaching` for generated membership pivots.
- Use `assignRole` or `assignGlobalRole` repeatedly instead of manually inserting assignment rows.
- Do not use raw IDs for scopes in reusable seeders.
- Run `access:sync` before assignment seeders so role and permission definitions exist.

A typical `DatabaseSeeder` order is:

```php
public function run(): void
{
    $this->call([
        DemoCompanySeeder::class,
        DemoAdminSeeder::class,
    ]);
}
```

Run sync before the database seeder in deployment scripts:

```bash
php artisan migrate --force
php artisan access:sync --force
php artisan db:seed --class=DatabaseSeeder --force
```

## Common Mistakes

Do not put scoped company roles in `global_roles`:

```php
// Avoid for company membership
'global_roles' => [
    'Owner' => [Permission::BillingManage],
],
```

Use scoped `roles` instead:

```php
'roles' => [
    CompanyRole::Owner->value => [Permission::BillingManage],
],
```

Do not assign a scoped role without a scope:

```php
// Avoid for scoped apps
$user->assignGlobalRole(CompanyRole::Owner->value);
```

Assign it in the scope:

```php
$user->in($company)->assignRole(CompanyRole::Owner->value);
```

Do not assume membership grants permissions. In apps scaffolded with `access:scope`, membership and access are separate. Attach the user to the scope and assign the access role when you want both.
