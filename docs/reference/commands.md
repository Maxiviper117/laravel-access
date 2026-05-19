---
title: Commands
---

# Commands

## `access:install`

Publishes config, migrations, and scaffolds reusable Action classes.

```bash
php artisan access:install
php artisan access:install --enum
```

With `--enum`, the command creates `app/Enums/Permission.php` if it does not already exist and updates `config/access.php` from `permission_enums => []` to `[\App\Enums\Permission::class]`.

The command also generates five Action classes under `app/Actions/Access/` following the standard Laravel Actions pattern:

| Class | Purpose |
|---|---|
| `CreateRole` | Create a dynamic custom role (scoped or global) |
| `DeleteRole` | Delete a custom role with system-role protection |
| `SyncRolePermissions` | Sync a full set of permissions onto a role |
| `AddPermissionToRole` | Add a single permission to a role |
| `RemovePermissionFromRole` | Remove a single permission from a role |

These classes accept `BackedEnum`, `string`, or `Role` model instances as role identifiers and are safe to use outside the `$user->in($scope)` context. See [Dynamic roles](/how-to/dynamic-roles) for usage examples.

## `access:scope`

Scaffolds app-owned team/group support. The group name can be renamed to match your domain.

```bash
php artisan access:scope
php artisan access:scope --name=company
php artisan access:scope --name=company --frontend=react
php artisan access:scope --name=company --notifications
php artisan access:scope --name=alumnus --plural=alumni
php artisan access:scope --name=workspace --force --migrate
```

Options:

`--name=`
: Scope name to use. Skips the interactive prompt.

`--singular=`
: Overrides the singular form.

`--plural=`
: Overrides the plural form.

`--frontend=`
: Invitation UI stack to generate. Supported values: `blade`, `react`, `vue`, `svelte`. In interactive mode, the command asks for this value. Non-interactive runs default to `blade`.

`--notifications`
: Generates a generic mail notification plus invitation creation/sending methods on the generated invitation controller. In interactive mode, the command asks whether to generate these helpers.

`--force`
: Overwrites existing scaffolded files.

`--migrate`
: Runs migrations after scaffolding.

`--no-concern`
: Skips patching `app/Models/User.php` with the generated `HasXxx` concern.

Generated files include renamed migrations, models, a membership pivot model, invitation model and controller, `HasXxx` concern, `EnsureXxxMembership` middleware, a role enum, invitation routes, invite registration views or Inertia pages, config updates, middleware alias registration, URL defaults, and starter scope permission cases appended to `app/Enums/Permission.php` when that file exists.

See [Scaffold team scopes](/how-to/scaffold-team-scopes) for the full generated architecture.

## `access:sync`

Syncs permission enum cases, role definitions, and role-permission attachments.

```bash
php artisan access:sync
php artisan access:sync --dry-run
php artisan access:sync --prune
php artisan access:sync --prune --force
```

Options:

`--dry-run`
: Reports changes without writing to the database.

`--prune`
: Deletes stale permissions and roles after confirmation.

`--force`
: Skips confirmation when pruning.

`access:sync` syncs definitions from config into the database. It does not assign roles to users. Assign scoped roles with `$user->in($scope)->assignRole(...)` and global roles with `$user->assignGlobalRole(...)`. See [Seed roles and permissions](/how-to/seed-roles-and-permissions).

## `access:clear`

Invalidates package permission cache entries.

```bash
php artisan access:clear
```

This invalidates Laravel Access cache keys without clearing unrelated application cache entries.

## `access:debug`

Shows roles and permissions for a user and optional scope.

```bash
php artisan access:debug user@example.com
php artisan access:debug user@example.com --scope=company:1
```

The scope string uses the configured `default_scope_model`; the prefix is descriptive and the ID is used for lookup.
