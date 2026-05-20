---
title: Debug Access
---

# Debug Access

Use `access:debug` to inspect a user in a scope.

```bash
php artisan access:debug david@example.com --scope=company:1
```

The command uses `default_scope_model` to resolve the scope ID. Configure it first:

```php
'default_scope_model' => App\Models\Company::class,
```

If permissions look stale after changing assignments or role definitions, clear the package cache:

```bash
php artisan access:clear
```

## Common Checks

If a user cannot perform an action, check these in order:

1. The user has the expected role in the expected scope.
2. The role includes the expected permission.
3. The policy is checking the same scope where the role was assigned.
4. The enum value matches the database permission name.
5. Cache was cleared after role or assignment changes.

## Scope Mismatches

This assignment:

```php
$user->in($companyA)->assignRole('Owner');
```

does not allow this check:

```php
$user->in($companyB)->can(Permission::UsersInvite);
```

That isolation is intentional. If access should apply across multiple companies, assign the role in each scope or use a global role for platform-level actions.
