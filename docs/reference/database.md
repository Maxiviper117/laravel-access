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
