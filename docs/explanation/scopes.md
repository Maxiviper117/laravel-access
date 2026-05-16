---
title: Scopes
---

# Scopes

Scoped checks make the target object visible in the code.

```php
$user->in($company)->can(Permission::UsersInvite);
```

There is no global current team or hidden tenant state. The scope is stored polymorphically using `scope_type` and `scope_id`, so the same package can work with a `Company`, `Team`, `Workspace`, or `Tenant` model.

Global assignments use a null scope. They are meant for platform-level access, not company membership.

## What Counts as a Scope

A scope is any Eloquent model that represents a boundary for authorization:

- `Company`
- `Team`
- `Workspace`
- `Tenant`
- `Project`

The package stores scope references polymorphically, so you are not locked into a single model name.

## What Scopes Do Not Do

Scopes do not prove membership by themselves. Your app still owns membership, invitations, billing state, and domain rules.

For example, this role assignment says the user has `Owner` access in a company:

```php
$user->in($company)->assignRole('Owner');
```

It does not create a `company_user` row or invite the user into the company. Keep those concepts in your application models.
