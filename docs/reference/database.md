---
title: Database
---

# Database

Laravel Access creates four tables.

## `access_permissions`

Stores permission definitions.

```text:line-numbers
id
name
label
description
is_system
resource
kind
target
ability
created_at
updated_at
```

`is_system` is `true` for definitions declared in `permission_enums`, `roles`, or `global_roles`. Runtime-created permission definitions use `false`. `access:sync --prune` only removes stale system-owned definitions.

The nullable `resource`, `kind`, `target`, and `ability` columns hold optional permission metadata for discovery and administration. `kind` is `resource` or `field`; `target` is null for a whole-resource permission and identifies the field for a field permission. Rows without metadata leave all four columns null. These values do not affect authorization, which continues to use the exact permission `name`.

## `access_roles`

Stores reusable role definitions.

```text:line-numbers
id
name
label
description
is_global
is_system
created_at
updated_at
```

## `access_role_permissions`

Connects roles to permissions.

```text:line-numbers
role_id
permission_id
```

## `access_assignments`

Assigns a role or direct permission to an actor, globally or in a scope.

```text:line-numbers
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

```text:line-numbers
actor_type, actor_id, role_id, scope_type, scope_id
actor_type, actor_id, permission_id, scope_type, scope_id
```

This prevents duplicate assignments for the same actor and scope.

The `add_global_access_uniqueness` migration adds database-generated keys so these constraints also cover global rows, whose scope columns are `null`. This prevents duplicate global role names and duplicate global role or direct permission assignments, including when concurrent requests attempt the same insert.

## Global Rows

Global rows have no scope:

```text:line-numbers
scope_type = null
scope_id = null
```

Scoped rows store both scope columns:

```text:line-numbers
scope_type = App\Models\Company
scope_id = 1
```

When upgrading an existing app, publish and run the new migration:

```bash
php artisan vendor:publish --tag=access-migrations
php artisan migrate
```

The permission ownership migration defaults existing permission rows to `is_system = false` so unknown rows are preserved. The next `access:sync` marks definitions still declared in config as system-owned. Definitions that were removed from config before this migration cannot be identified automatically; review and remove any such old rows explicitly if needed.

The permission metadata migration adds the four nullable metadata columns. Existing permission rows remain unclassified until their enum definition or dynamic registration supplies metadata.

The migration expects existing global role names and global assignments to be unique. If earlier concurrent writes created duplicates, resolve those rows before running the migration so the unique indexes can be created.

## Scope Scaffold Tables

The optional `access:scope` command generates additional application tables such as:

```text:line-numbers
companies
company_members
company_invitations
users.current_company_id
```

Those tables are not Laravel Access authorization tables. They represent membership, invitations, and current scope state for your app.

Laravel Access still stores permissions, roles, and assignments in the `access_*` tables. A common company setup writes to both systems:

```php:line-numbers
$company->users()->attach($user, ['role' => CompanyRole::Admin->value]);
$user->in($company)->assignRole(CompanyRole::Admin);
```

The membership row says the user belongs to the company. The access assignment says the user has the `Admin` authorization role inside that company.
