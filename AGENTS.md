# AGENTS.md

## Repository Context

This is `maxiviper117/laravel-access`, a Laravel package for explicit scoped and global role/permission authorization.

Core ideas:

- Scoped access is explicit: use `$user->in($scope)->...`.
- Global access is separate: use `$user->assignGlobalRole(...)` and `$user->canGlobally(...)`.
- Permissions are enum-first and synced into the `access_*` tables.
- `permission_enums` is the active config key. Do not reintroduce the old singular `permission_enum` model.
- `access:scope` scaffolds app-owned membership/invitation/current-scope code. It should not make Laravel Access depend on hidden tenant state.
- `access:seeder` generates an editable starter seeder for app membership plus scoped Laravel Access assignment.
- `access:scope` generates a scope role enum, but normal permission cases belong in `App\Enums\Permission`. Modular permission enums are supported only by listing additional classes in `permission_enums`.

## Working Agreements

- Read existing code and tests before changing behavior.
- Prefer the package's current patterns over adding new abstractions.
- Keep changes narrow and avoid unrelated refactors.
- Do not add production dependencies without explicit user approval.
- Use `rg` for searching.
- Use `apply_patch` for manual edits.
- Do not revert user changes or unrelated dirty files.
- Keep generated scaffold code deterministic and easy for application developers to edit after publishing.
- Keep this `AGENTS.md` up to date when code, commands, architecture, or documentation workflows change.

## No Fallbacks

Never add fallbacks, never think about legacy fallbacks, and do not worry about breaking changes unless explicitly told. Do not add extra code, checks, branches, compatibility layers, or migration paths that were not explicitly requested.

## Commands

Use these commands from the repository root:

```bash
composer test
composer analyse
composer format
pnpm docs:build
```

Focused alternatives:

```bash
vendor/bin/pest tests/Commands/ScopeAccessCommandTest.php
vendor/bin/pest tests/Commands/AccessSeederCommandTest.php
vendor/bin/pest tests/Commands/SyncAccessCommandTest.php
vendor/bin/pint --dirty
```

Run `pnpm docs:build` after documentation changes. Run the relevant Pest tests after PHP behavior changes, and run the full suite before finalizing broad command/config changes.

## Code Style

- PHP target is PHP 8.4.
- Laravel support currently spans Illuminate 11, 12, and 13 constraints.
- Use typed method signatures and enum-aware APIs where practical.
- Keep public command output concise and actionable.
- Generated app code should use `App\...` namespaces, not package namespaces, unless it is intentionally calling back into this package.
- Avoid comments that restate obvious code. Comments in generated code should help app developers understand what to customize.

## Authorization Model

Keep these distinctions clear in code and docs:

- `roles`: scoped role definitions, assigned with `$user->in($scope)->assignRole(...)`.
- `global_roles`: app-wide role definitions, assigned with `$user->assignGlobalRole(...)`.
- `access:sync`: syncs permission and role definitions. It does not assign roles to users.
- Generated membership tables from `access:scope` are app state, not Laravel Access authorization tables.
- App membership and Laravel Access role assignment are separate writes.

## `access:scope` Rules

When editing `ScopeAccessCommand`:

- Preserve rename support for `team`, `company`, `workspace`, `tenant`, and irregular plural overrides.
- Derive names with Laravel `Str` helpers.
- Keep route file registration compatible with Laravel's `bootstrap/app.php ->withRouting(... then: ...)` style.
- Keep Inertia render strings and generated file paths case-aligned. Current convention is lowercase `auth/...`.
- Generated Inertia pages should target Inertia v3:
  - React: `@inertiajs/react` `<Form>`
  - Vue: `@inertiajs/vue3` `<Form>`
  - Svelte: `@inertiajs/svelte` `<Form>` and `$props()`
- Invitation Blade/Inertia pages should remain basic Tailwind starter UI.
- Do not generate `<Scope>Permission` by default. Add starter scope permission cases to `App\Enums\Permission` when the file exists.

## `access:seeder` Rules

When editing `AccessSeederCommand`:

- Preserve rename support for `team`, `company`, `workspace`, `tenant`, and irregular plural overrides.
- Generate editable app seeders under `database/seeders`, not package seeders.
- Keep app membership writes and Laravel Access role assignment as separate statements.
- Do not make the generated seeder assign roles globally for scoped examples.

## Documentation Rules

- Keep docs explicit about scoped vs global-only setup.
- Use `Permission.php` as the recommended canonical enum in examples.
- Mention multiple permission enums only as an intentional modular setup.
- Update sidebar config in `.vitepress/config.mts` when adding new docs pages.
- Prefer short, practical examples over exhaustive framework explanation.
- Use `:line-numbers` on non-bash fenced code blocks in docs.

## Verification Expectations

Before finalizing:

- PHP command/config changes: run relevant Pest tests, usually full `composer test`.
- Docs changes: run `pnpm docs:build`.
- Formatting: run `vendor/bin/pint --dirty` or `composer format` when PHP files changed.

If a verification command cannot be run, state that clearly in the final response.
