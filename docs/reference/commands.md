---
title: Commands
---

# Commands

## `access:install`

Publishes config and migrations.

```bash
php artisan access:install
php artisan access:install --enum
```

With `--enum`, the command creates `app/Enums/Permission.php` if it does not already exist and updates `config/access.php` from `permission_enum => null` to `\App\Enums\Permission::class`.

## `access:scope`

Scaffolds app-owned team/group support. The group name can be renamed to match your domain.

```bash
php artisan access:scope
php artisan access:scope --name=company
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

`--force`
: Overwrites existing scaffolded files.

`--migrate`
: Runs migrations after scaffolding.

`--no-concern`
: Skips patching `app/Models/User.php` with the generated `HasXxx` concern.

Generated files include renamed migrations, models, a membership pivot model, invitation model and controller, `HasXxx` concern, `EnsureXxxMembership` middleware, role and permission enums, invitation routes, invite registration views, config updates, middleware alias registration, and URL defaults.

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
