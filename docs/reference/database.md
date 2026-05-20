---
title: Database
---

# Database

Laravel Access creates four tables.

## `access_permissions`

Stores permission definitions.

```text
id
name
label
description
created_at
updated_at
```

## `access_roles`

Stores reusable role definitions.

```text
id
name
label
description
is_global
created_at
updated_at
```

## `access_role_permissions`

Connects roles to permissions.

```text
role_id
permission_id
```

## `access_assignments`

Assigns a role or direct permission to an actor, globally or in a scope.

```text
id
actor_type
actor_id
role_id
permission_id
scope_type
scope_id
created_at
updated_at
```

Exactly one of `role_id` or `permission_id` should be present.

## Unique Assignments

The assignments table has separate unique indexes for role assignments and direct permission assignments:

```text
actor_type, actor_id, role_id, scope_type, scope_id
actor_type, actor_id, permission_id, scope_type, scope_id
```

This prevents duplicate assignments for the same actor and scope.

## Global Rows

Global rows have no scope:

```text
scope_type = null
scope_id = null
```

Scoped rows store both scope columns:

```text
scope_type = App\Models\Company
scope_id = 1
```

## Scope Scaffold Tables

The optional `access:scope` command generates additional application tables such as:

```text
companies
company_members
company_invitations
users.current_company_id
```

Those tables are not Laravel Access authorization tables. They represent membership, invitations, and current scope state for your app.

Laravel Access still stores permissions, roles, and assignments in the `access_*` tables. A common company setup writes to both systems:

```php
$company->users()->attach($user, ['role' => CompanyRole::Admin->value]);
$user->in($company)->assignRole(CompanyRole::Admin);
```

The membership row says the user belongs to the company. The access assignment says the user has the `Admin` authorization role inside that company.
