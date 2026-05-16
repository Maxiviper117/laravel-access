---
title: Mental Model
---

# Mental Model

Laravel Access has one authorization data model:

1. A permission is a named ability, such as `users.invite`.
2. A role is a named bundle of permissions, such as `Owner`.
3. A scope is where a role applies, such as a company.
4. A user can have roles globally or inside a scope.
5. Laravel policies decide final authorization rules.

The package intentionally encourages permission checks instead of role checks:

```php
$user->in($company)->can(Permission::UsersInvite);
```

Role checks still exist for exceptional cases, but policy code should normally ask whether a user has an ability, not whether they have a specific title.

## Permission First

Permissions are more stable than roles. A role named `Admin` might gain or lose abilities as the product changes, but a policy usually needs one specific answer:

```php
return $user->in($company)->can(Permission::UsersInvite);
```

This keeps policy code readable even when roles evolve.

## Explicit Scope

The scope is part of the check:

```php
$user->in($company)->can(Permission::CompanyUpdate);
```

That is deliberate. Laravel Access does not maintain a global current team or tenant ID. If you can read the authorization code, you can see which company or workspace it applies to.

## Global Access

Global access exists for platform-level roles:

```php
$user->assignGlobalRole('Platform Admin');
$user->canGlobally(Permission::SystemManage);
```

Use global roles for app-wide administration. Use scoped roles for company, team, workspace, or tenant access.
