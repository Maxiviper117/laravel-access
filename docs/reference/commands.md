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

With `--enum`, the command creates `app/Enums/Permission.php` if it does not already exist.

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
