---
title: Laravel Access
---

# Laravel Access

Laravel Access is a small Laravel permission package for apps with companies, teams, workspaces, or tenants.

The core shape is:

```php
$user->in($company)->assignRole('Owner');

$user->in($company)->can(Permission::UsersInvite);
```

Users receive roles inside an explicit scope. Roles contain permissions. Laravel policies decide whether the action is allowed.

## Why It Exists

Laravel already has excellent authorization primitives: gates, policies, middleware, and controller helpers. Laravel Access does not replace them. It gives you a small database-backed access layer that answers one question clearly:

> Does this actor have this permission in this scope?

That keeps application code explicit:

```php
$user->in($company)->can(Permission::RolesManage);
```

There is no `guard_name`, no global current team state, and no hidden tenant context. The scope is visible at the call site.

## Documentation

This documentation follows the Diátaxis structure:

- [Tutorials](/tutorials/getting-started) walk through a complete first setup.
- [How-to guides](/how-to/define-permissions) solve specific tasks.
- [Explanation](/explanation/mental-model) describes the design and tradeoffs.
- [Reference](/reference/configuration) lists exact APIs, commands, and schema.

## Common Workflow

1. Define permissions in an enum.
2. Define default roles in `config/access.php`.
3. Run `php artisan access:sync`.
4. Assign scoped roles with `$user->in($scope)->assignRole(...)`.
5. Check permissions inside policies.
6. Share permission maps to Inertia only for UI decisions.

## Quick Example

```php
use App\Enums\Permission;

public function inviteUsers(User $user, Company $company): bool
{
    return $user->in($company)->can(Permission::UsersInvite);
}
```

Start with [Getting started](/tutorials/getting-started).
