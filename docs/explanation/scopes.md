---
title: Scopes
---

# Scopes

Scoped checks make the target object visible in the code.

```php:line-numbers
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

```php:line-numbers
$user->in($company)->assignRole(CompanyRole::Owner);
```

It does not create a `company_user` row or invite the user into the company. Keep those concepts in your application models.

The optional `access:scope` command can generate that application layer for you, but the separation remains:

- Generated scope tables track membership, invitations, and the user's current scope.
- Laravel Access tables track permissions, roles, and assignments.

For example:

```php:line-numbers
$company->users()->attach($user, ['role' => CompanyRole::Admin->value]);
$user->in($company)->assignRole(CompanyRole::Admin);
```

The first line is membership. The second line is authorization.

## Optional Scope Scaffolding

::: warning Laravel Starter Kit Team Support
Do not use `access:scope` if you already enabled team support in the official Laravel starter kit (`laravel new --teams`). That starter kit generates its own membership, invitation, and scope-switching code. `access:scope` assumes a fresh app without those files and will conflict with or duplicate the starter kit's structure.
:::

If you want Laravel Access to create that application-owned membership layer for you, run `access:scope`.

```bash
php artisan access:scope --name=company
```

The command scaffolds first-party app code: a `Company` model, membership and invitation tables, `HasCompanies`, `EnsureCompanyMembership`, role and permission enums, invitation routes, and current-company URL defaults. The generated code is still yours to edit.

The authorization API does not change:

```php:line-numbers
$user->in($company)->assignRole(CompanyRole::Admin);
$user->in($company)->can(Permission::UsersInvite);
```

Read [Scaffold team scopes](/how-to/scaffold-team-scopes) for the full workflow.
