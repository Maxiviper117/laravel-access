# PRD: Simple Laravel Permissions Package

## Working name

`laravel-access`

package name:

```bash
composer require maxiviper117/laravel-access
```


## 1. Product summary

Build a small Laravel 13 permission package designed for personal project use, especially apps with companies, teams, tenants, or workspaces.

The package should be drastically simpler than Spatie Laravel Permission by focusing on one mental model:

> Users get roles inside a scope. Roles contain permissions. Laravel policies decide whether an action is allowed.

The package should not try to replace Laravel Gates or Policies. It should provide a clean database-backed way to assign roles and permissions, then expose an easy API for checking those permissions from controllers, policies, middleware, and Inertia props.

## 2. Problem

Spatie Laravel Permission is powerful, flexible, and widely used, but it can feel difficult when building normal Laravel SaaS-style apps with teams or companies.

Pain points this package should solve:

* `guard_name` is noisy for most applications.
* Teams support depends on global state like `setPermissionsTeamId()`.
* It is easy to confuse global roles with team-scoped roles.
* The API encourages checking roles directly instead of permissions.
* Permissions are usually raw strings, which makes typos easy.
* Setup requires understanding several moving parts before the app has any authorization value.
* The package feels like a second authorization system instead of a simple helper around Laravel policies.

## 3. Goals

### Primary goals

* Make permission checks easy to read.
* Make company/team/tenant scoping explicit.
* Avoid hidden global state.
* Avoid `guard_name` by default.
* Encourage permission-based checks instead of role-based checks.
* Integrate cleanly with Laravel 13 Gates and Policies.
* Provide enum-first permissions.
* Provide a simple sync command for roles and permissions.
* Keep the package small enough to understand fully.

### Secondary goals

* Support Inertia apps cleanly.
* Support starter-kit apps with teams enabled.
* Make tests easy to write.
* Keep the database schema understandable.
* Allow direct permissions, but discourage them for normal use.
* Allow global roles for app-wide admin access.

## 4. Non-goals

This package should not try to support every Spatie use case.

Out of scope:

* Multi-guard support by default.
* Complex wildcard permission systems.
* Multiple authentication providers unless explicitly added later.
* Package-level UI.
* Full RBAC administration dashboard.
* Replacing Laravel Policies.
* Supporting old Laravel versions.
* Supporting every possible polymorphic model as a first-class use case.
* Deep compatibility with Spatie Laravel Permission internals.

## 5. Target user

Primary user:

* A Laravel developer building business apps, SaaS apps, internal portals, or company/team-based systems.

More specifically:

* Uses Laravel 13.
* Uses Inertia with Svelte, React, or Vue.
* Uses a `Company`, `Team`, `Workspace`, or `Tenant` model.
* Wants simple roles like `Owner`, `Admin`, and `Member`.
* Wants to check permissions like `users.invite`, `roles.manage`, and `company.update`.
* Does not want to think about guards, pivot table internals, or global team state.

## 6. Core mental model

The package should be explainable in five sentences:

1. A permission is a named ability, such as `users.invite`.
2. A role is a named bundle of permissions, such as `Owner` or `Admin`.
3. A scope is where the role applies, such as a company or team.
4. A user can have a role globally or inside a scope.
5. Laravel Policies decide final authorization rules.

Example:

```php
$user->in($company)->assignRole('Owner');

$user->in($company)->can(Permission::UsersInvite);
```

## 7. Design principles

### 7.1 Permission-first

Application code should usually check permissions, not roles.

Preferred:

```php
$user->in($company)->can(Permission::UsersInvite);
```

Discouraged:

```php
$user->in($company)->hasRole('Owner');
```

Role checks should still exist for rare cases, but the docs and API should steer users toward permissions.

### 7.2 Explicit scoping

Scoped permission checks must make the scope visible in the code.

Preferred:

```php
$user->in($company)->can(Permission::UsersManage);
```

Not allowed:

```php
setPermissionsTeamId($company->id);
$user->can('users.manage');
```

There should be no global mutable “current team” state.

### 7.3 Laravel-native authorization

The package should plug into Laravel’s existing authorization concepts.

Controllers should still use:

```php
$this->authorize('inviteUsers', $company);
```

Policies should use the package internally:

```php
public function inviteUsers(User $user, Company $company): bool
{
    return $user->in($company)->can(Permission::UsersInvite);
}
```

### 7.4 Enum-first permissions

Permissions should be represented as PHP enums by default.

```php
enum Permission: string
{
    case UsersView = 'users.view';
    case UsersInvite = 'users.invite';
    case UsersManage = 'users.manage';
    case RolesManage = 'roles.manage';
    case CompanyUpdate = 'company.update';
}
```

Raw strings should be accepted, but enums should be the documented default.

### 7.5 Simple defaults, advanced escape hatches

The common path should require very little configuration.

Advanced use cases can exist, but should not pollute the everyday API.

## 8. User stories

### 8.1 Install package

As a developer, I want to install and configure the package quickly so that I can start protecting routes and actions.

Acceptance criteria:

* Running `php artisan access:install` publishes config and migrations.
* The install command can generate a permission enum.
* The install command can optionally configure a default scope model.
* The install command explains the next steps.

### 8.2 Define permissions

As a developer, I want to define permissions in one enum so that typos are reduced.

Acceptance criteria:

* I can create an enum at `app/Enums/Permission.php`.
* I can sync the enum into the database.
* Missing permissions are created automatically.
* Stale permissions can be reported before deletion.

### 8.3 Define roles

As a developer, I want to define default roles in config so that they can be synced consistently.

Acceptance criteria:

* I can define roles in `config/access.php`.
* Each role can list its permissions.
* Running `php artisan access:sync` creates roles and attaches permissions.
* The command can show a dry-run diff.

### 8.4 Assign scoped roles

As a developer, I want to assign a user a role inside a company.

Acceptance criteria:

```php
$user->in($company)->assignRole('Admin');
```

* The role applies only inside that company.
* The same user can have a different role in another company.
* The assignment is clear in the database.

### 8.5 Check scoped permissions

As a developer, I want to check whether a user can do something inside a company.

Acceptance criteria:

```php
$user->in($company)->can(Permission::UsersInvite);
```

* Returns `true` if the user has the permission directly or through a scoped role.
* Returns `false` if the permission only exists in another scope.
* Accepts enum cases and strings.

### 8.6 Assign global roles

As a developer, I want to assign platform-level roles that are not tied to a company.

Acceptance criteria:

```php
$user->assignGlobalRole('Platform Admin');
$user->hasGlobalRole('Platform Admin');
```

* Global roles are clearly separate from scoped roles.
* Global permissions can be checked separately.
* Optional global override can be configured for Laravel Gate.

### 8.7 Use package inside Laravel policies

As a developer, I want to use this package inside policies without making controllers messy.

Acceptance criteria:

```php
public function update(User $user, Company $company): bool
{
    return $user->in($company)->can(Permission::CompanyUpdate);
}
```

* Policies remain the recommended place for object-specific authorization.
* Controllers can stay Laravel-native.

### 8.8 Share permissions with Inertia

As a developer, I want to send a simple permission map to the frontend.

Acceptance criteria:

```php
'access' => Access::for($user)->in($company)->toArray([
    Permission::UsersInvite,
    Permission::RolesManage,
    Permission::CompanyUpdate,
]);
```

Outputs:

```php
[
    'users.invite' => true,
    'roles.manage' => false,
    'company.update' => true,
]
```

## 9. Proposed API

### 9.1 User trait

The user model uses one trait:

```php
use Maxiviper117\Access\Concerns\HasAccess;

class User extends Authenticatable
{
    use HasAccess;
}
```

### 9.2 Scoped role assignment

```php
$user->in($company)->assignRole('Owner');
$user->in($company)->assignRole('Admin');
$user->in($company)->removeRole('Admin');
```

### 9.3 Scoped role checks

```php
$user->in($company)->hasRole('Owner');
$user->in($company)->hasAnyRole(['Owner', 'Admin']);
```

These methods exist, but should not be the main recommended authorization style.

### 9.4 Scoped permission checks

```php
$user->in($company)->can(Permission::UsersInvite);
$user->in($company)->cannot(Permission::RolesManage);
$user->in($company)->hasPermission(Permission::CompanyUpdate);
```

Possible naming decision:

* Prefer `can()` because it matches Laravel.
* Keep `hasPermission()` as a more explicit alias.

### 9.5 Global roles

```php
$user->assignGlobalRole('Platform Admin');
$user->removeGlobalRole('Platform Admin');
$user->hasGlobalRole('Platform Admin');
```

### 9.6 Global permissions

```php
$user->canGlobally(Permission::SystemManage);
$user->giveGlobalPermission(Permission::SystemManage);
```

Use sparingly.

### 9.7 Direct scoped permissions

```php
$user->in($company)->givePermission(Permission::UsersInvite);
$user->in($company)->revokePermission(Permission::UsersInvite);
```

Direct permissions should be supported, but role-based permission bundles should be preferred.

### 9.8 Access facade

```php
Access::role('Owner')->allows([
    Permission::UsersInvite,
    Permission::UsersManage,
]);

Access::for($user)->in($company)->can(Permission::UsersInvite);
```

### 9.9 Middleware

Route middleware examples:

```php
Route::middleware('access:users.invite,company')->group(function () {
    // ...
});
```

Possible route usage:

```php
Route::post('/companies/{company}/users/invite', InviteUserController::class)
    ->middleware('access:users.invite,company');
```

The middleware should resolve `{company}` from route parameters and use it as the scope.

However, policies should still be recommended for most cases.

## 10. Configuration

Example `config/access.php`:

```php
use App\Enums\Permission;
use App\Models\Company;

return [
    'user_model' => App\Models\User::class,

    'permission_enum' => Permission::class,

    'default_scope_model' => Company::class,

    'cache' => [
        'enabled' => true,
        'key' => 'access.permissions',
        'ttl' => null,
    ],

    'global_roles' => [
        'Platform Admin' => [
            Permission::SystemManage,
        ],
    ],

    'roles' => [
        'Owner' => [
            Permission::UsersView,
            Permission::UsersInvite,
            Permission::UsersManage,
            Permission::RolesManage,
            Permission::CompanyUpdate,
        ],

        'Admin' => [
            Permission::UsersView,
            Permission::UsersInvite,
            Permission::CompanyUpdate,
        ],

        'Member' => [
            Permission::UsersView,
        ],
    ],

    'gate_before' => [
        'enabled' => true,
        'global_role' => 'Platform Admin',
    ],
];
```

## 11. Database design

### 11.1 Tables

Recommended tables:

```text
access_permissions
access_roles
access_role_permissions
access_assignments
```

### 11.2 `access_permissions`

```text
id
name
label nullable
description nullable
created_at
updated_at
```

Example:

```text
users.invite
roles.manage
company.update
```

### 11.3 `access_roles`

```text
id
name
label nullable
description nullable
is_global boolean default false
created_at
updated_at
```

Roles themselves are reusable definitions.

Example:

```text
Owner
Admin
Member
Platform Admin
```

### 11.4 `access_role_permissions`

```text
role_id
permission_id
```

### 11.5 `access_assignments`

This table assigns either a role or a direct permission to an actor.

```text
id
actor_type
actor_id
role_id nullable
permission_id nullable
scope_type nullable
scope_id nullable
created_at
updated_at
```

Rules:

* `role_id` or `permission_id` must be present.
* Both should not be present at the same time.
* `scope_type` and `scope_id` are nullable for global assignments.
* Scoped assignments require both `scope_type` and `scope_id`.

Unique indexes:

```text
actor_type, actor_id, role_id, scope_type, scope_id
actor_type, actor_id, permission_id, scope_type, scope_id
```

## 12. Commands

### 12.1 Install command

```bash
php artisan access:install
```

Responsibilities:

* Publish config.
* Publish migrations.
* Optionally generate `app/Enums/Permission.php`.
* Optionally add trait instructions for the User model.
* Optionally create a starter access config.

### 12.2 Sync command

```bash
php artisan access:sync
```

Responsibilities:

* Sync permissions from enum.
* Sync roles from config.
* Attach configured permissions to roles.
* Warn about stale permissions.
* Warn about stale roles.
* Clear permission cache.

Options:

```bash
php artisan access:sync --dry-run
php artisan access:sync --prune
php artisan access:sync --force
```

### 12.3 Cache reset command

```bash
php artisan access:clear
```

Responsibilities:

* Clear cached role and permission lookup data.

### 12.4 Debug command

```bash
php artisan access:debug user@example.com --scope=company:1
```

Output example:

```text
User: David <david@example.com>
Scope: Company #1

Roles:
- Owner

Permissions via roles:
- users.view
- users.invite
- users.manage
- roles.manage
- company.update

Direct permissions:
- none

Global roles:
- none
```

This command is important because authorization bugs are often hard to see.

## 13. Laravel integration

### 13.1 Gates

The package may optionally register permissions with Laravel Gate.

However, because scoped permissions require a scope, the package should not pretend all permission checks are scope-less.

Possible usage:

```php
Gate::define('users.invite', function (User $user, Company $company) {
    return $user->in($company)->can(Permission::UsersInvite);
});
```

Optional helper:

```php
Access::defineScopedGates(Permission::class, Company::class);
```

But this should be treated as optional.

### 13.2 Policies

Recommended policy example:

```php
class CompanyPolicy
{
    public function inviteUsers(User $user, Company $company): bool
    {
        return $user->in($company)->can(Permission::UsersInvite);
    }

    public function manageRoles(User $user, Company $company): bool
    {
        return $user->in($company)->can(Permission::RolesManage);
    }

    public function update(User $user, Company $company): bool
    {
        return $user->in($company)->can(Permission::CompanyUpdate);
    }
}
```

### 13.3 Inertia

Example middleware share:

```php
'access' => fn () => $request->user() && $company
    ? Access::for($request->user())->in($company)->toArray([
        Permission::UsersInvite,
        Permission::RolesManage,
        Permission::CompanyUpdate,
    ])
    : [],
```

Frontend usage:

```ts
if ($page.props.access['users.invite']) {
    // show invite button
}
```

## 14. Caching

Caching should be simple and safe.

Requirements:

* Cache permission lookups per actor and scope.
* Clear cache when roles, permissions, or assignments change.
* Provide `access:clear` command.
* Avoid making cache behavior surprising during tests.

Possible cache key:

```text
access:{actor_type}:{actor_id}:{scope_type}:{scope_id}
```

Example:

```text
access:App\Models\User:1:App\Models\Company:10
```

Test environment should disable cache by default.

## 15. Testing requirements

The package should include tests for:

* Installing migrations.
* Syncing permissions from enum.
* Syncing roles from config.
* Assigning scoped roles.
* Removing scoped roles.
* Checking scoped permissions through roles.
* Checking direct scoped permissions.
* Ensuring scoped permissions do not leak across scopes.
* Assigning global roles.
* Checking global permissions.
* Gate before override for platform admins.
* Middleware checks.
* Cache invalidation.
* Inertia permission map generation.

## 16. Example implementation flow

### 16.1 Define enum

```php
namespace App\Enums;

enum Permission: string
{
    case UsersView = 'users.view';
    case UsersInvite = 'users.invite';
    case UsersManage = 'users.manage';
    case RolesManage = 'roles.manage';
    case CompanyUpdate = 'company.update';
    case SystemManage = 'system.manage';
}
```

### 16.2 Configure roles

```php
use App\Enums\Permission;

return [
    'roles' => [
        'Owner' => [
            Permission::UsersView,
            Permission::UsersInvite,
            Permission::UsersManage,
            Permission::RolesManage,
            Permission::CompanyUpdate,
        ],

        'Admin' => [
            Permission::UsersView,
            Permission::UsersInvite,
            Permission::CompanyUpdate,
        ],

        'Member' => [
            Permission::UsersView,
        ],
    ],
];
```

### 16.3 Sync database

```bash
php artisan access:sync
```

### 16.4 Assign user role

```php
$user->in($company)->assignRole('Owner');
```

### 16.5 Check permission

```php
$user->in($company)->can(Permission::UsersInvite);
```

### 16.6 Use in policy

```php
public function inviteUsers(User $user, Company $company): bool
{
    return $user->in($company)->can(Permission::UsersInvite);
}
```

## 17. Suggested package structure

```text
src/
  AccessServiceProvider.php
  Access.php
  Concerns/
    HasAccess.php
  Contracts/
    AccessActor.php
    AccessScope.php
  Data/
    AccessContext.php
  Models/
    Role.php
    Permission.php
    Assignment.php
  Support/
    PermissionNormalizer.php
    RoleRegistrar.php
    PermissionRegistrar.php
    AccessChecker.php
    AccessCache.php
  Commands/
    InstallAccessCommand.php
    SyncAccessCommand.php
    ClearAccessCommand.php
    DebugAccessCommand.php
  Middleware/
    EnsureHasPermission.php
config/
  access.php
resources/stubs/
  PermissionEnum.stub
  access-config.stub
database/migrations/
  create_access_tables.php.stub
tests/
```

## 18. Naming decisions

### Package name

Recommended internal package name:

```text
Laravel Access
```

Composer package:

```text
maxiviper117/laravel-access
```

Namespace:

```php
Maxiviper117\Access
```

Facade:

```php
Access
```

Trait:

```php
HasAccess
```

Scoped context method:

```php
$user->in($company)
```

This is short and expressive.

## 19. Main risks

### 19.1 Accidentally rebuilding Spatie

Risk:

The package slowly grows into a full Spatie clone.

Mitigation:

* Keep strict non-goals.
* Avoid advanced wildcard permissions at first.
* Avoid UI.
* Avoid multi-guard support until there is a real need.

### 19.2 Confusing relationship with Laravel Gates

Risk:

Users may be unsure whether to use `$user->can()`, `$user->in($company)->can()`, Gates, or Policies.

Mitigation:

Document the rule clearly:

* Use `$user->in($scope)->can()` inside policies and backend logic.
* Use Laravel Policies from controllers.
* Use Inertia permission maps for frontend UI.

### 19.3 Scope model complexity

Risk:

Different apps have different names for the scope model.

Mitigation:

Use polymorphic `scope_type` and `scope_id`, but keep the default config simple.

### 19.4 Performance

Risk:

Permission checks could become query-heavy.

Mitigation:

* Cache resolved permissions per user and scope.
* Eager load assignments where useful.
* Provide a batch permission map API.

## 20. MVP scope

The first version should include only:

* User trait.
* Permission enum support.
* Role and permission models.
* Scoped role assignment.
* Scoped permission checks.
* Global role assignment.
* Config-based role sync.
* Install command.
* Sync command.
* Clear cache command.
* Basic middleware.
* Tests.

Do not build:

* UI.
* Wildcard permissions.
* Multi-guard UI.
* Team auto-discovery.
* Complex admin panel integration.

## 21. Future additions and quality-of-life roadmap

The MVP should stay small. These additions should be treated as later upgrades, not initial requirements.

The goal of these future additions is to make the package feel excellent to use without turning it into a large authorization framework.

## 21.1 Developer experience additions

### 21.1.1 Permission generator command

Add a command that generates enum cases from plain permission names.

```bash
php artisan access:make-permission users.invite users.manage roles.manage
```

Generated enum output:

```php
enum Permission: string
{
    case UsersInvite = 'users.invite';
    case UsersManage = 'users.manage';
    case RolesManage = 'roles.manage';
}
```

Acceptance criteria:

* Converts dot-notated names into clean enum case names.
* Does not duplicate existing enum cases.
* Can optionally sort permissions alphabetically.
* Can optionally group permissions by prefix.

### 21.1.2 Permission diff command

Add a command to compare enum permissions, config roles, and database records.

```bash
php artisan access:diff
```

Output example:

```text
Missing in database:
- users.archive

Unused in config:
- billing.manage

Roles with missing permissions:
- Owner references reports.view, but it does not exist in the enum
```

This would make permission drift easier to debug.

### 21.1.3 Interactive role builder

Add an interactive command for creating or editing role definitions in config.

```bash
php artisan access:role Owner
```

Example flow:

```text
Select permissions for Owner:
[x] users.view
[x] users.invite
[x] users.manage
[x] roles.manage
[ ] billing.manage
```

This should update `config/access.php` or optionally output a copy-paste block.

### 21.1.4 Access doctor command

Add a diagnostic command for common mistakes.

```bash
php artisan access:doctor
```

Checks:

* User model has the package trait.
* Configured permission enum exists.
* Configured scope model exists.
* Migrations have run.
* Role config references valid permissions.
* Cache is working.
* Duplicate assignments do not exist.
* Direct permissions are not overused.

This would be especially useful when returning to a project after a long break.

### 21.1.5 Explain command

Add a command that explains why a user can or cannot perform an action.

```bash
php artisan access:explain user@example.com users.invite --scope=company:1
```

Output example:

```text
Result: allowed

Reason:
- User has role Owner in Company #1
- Owner includes permission users.invite

Source:
- access_assignments #15
- access_role_permissions #4
```

This could become one of the best debugging features in the package.

## 21.2 Laravel and policy quality-of-life additions

### 21.2.1 Policy helper trait

Add a trait for policies to make scoped permission checks shorter.

```php
class CompanyPolicy
{
    use ChecksAccess;

    public function inviteUsers(User $user, Company $company): bool
    {
        return $this->allows($user, Permission::UsersInvite, $company);
    }
}
```

This keeps policies readable while avoiding repeated `in($company)->can(...)` calls.

### 21.2.2 Policy generator

Add a command that creates a policy from selected permissions.

```bash
php artisan access:make-policy CompanyPolicy --scope=Company
```

Generated methods could map cleanly to permissions:

```php
public function inviteUsers(User $user, Company $company): bool
{
    return $user->in($company)->can(Permission::UsersInvite);
}
```

### 21.2.3 Route macro for scoped permission middleware

Add a route macro for cleaner routes.

```php
Route::post('/companies/{company}/users/invite', InviteUserController::class)
    ->requiresPermission(Permission::UsersInvite, scope: 'company');
```

This would be clearer than string-heavy middleware.

### 21.2.4 Form request authorization helper

Add a helper for using scoped permissions inside Form Requests.

```php
public function authorize(): bool
{
    return access($this->user())
        ->in($this->route('company'))
        ->can(Permission::UsersInvite);
}
```

Optional shorter version:

```php
return $this->user()->in($this->company())->can(Permission::UsersInvite);
```

## 21.3 Inertia and frontend additions

### 21.3.1 Inertia permission share helper

Add a built-in helper for sharing permissions.

```php
Access::shareWithInertia([
    Permission::UsersInvite,
    Permission::RolesManage,
    Permission::CompanyUpdate,
]);
```

Output:

```ts
$page.props.access = {
    permissions: {
        'users.invite': true,
        'roles.manage': false,
        'company.update': true,
    },
    roles: ['Owner'],
}
```

### 21.3.2 TypeScript permission generator

Generate a TypeScript permission object from the PHP enum.

```bash
php artisan access:typescript
```

Generated file:

```ts
export const Permission = {
    UsersView: 'users.view',
    UsersInvite: 'users.invite',
    UsersManage: 'users.manage',
    RolesManage: 'roles.manage',
    CompanyUpdate: 'company.update',
} as const;

export type Permission = typeof Permission[keyof typeof Permission];
```

This would make frontend permission checks type-safe.

### 21.3.3 Frontend `can()` helper

Provide a tiny frontend helper that works with Inertia props.

```ts
can(Permission.UsersInvite)
cannot(Permission.RolesManage)
```

For Svelte:

```ts
import { can } from '@/lib/access';

if (can(Permission.UsersInvite)) {
    // show button
}
```

### 21.3.4 Permission-aware navigation helper

Allow navigation items to declare required permissions.

```ts
const nav = [
    {
        label: 'Users',
        href: '/users',
        permission: Permission.UsersView,
    },
    {
        label: 'Roles',
        href: '/roles',
        permission: Permission.RolesManage,
    },
];

const visibleNav = filterByAccess(nav);
```

This would reduce repeated conditional UI code.

## 21.4 Role management additions

### 21.4.1 Role presets

Support predefined role presets for common app types.

```bash
php artisan access:preset company
```

Possible presets:

* `company`
* `saas`
* `admin-panel`
* `project-management`
* `crm`

Example `company` preset:

```php
'roles' => [
    'Owner' => [...],
    'Admin' => [...],
    'Member' => [...],
]
```

### 21.4.2 Role cloning

Allow one role to be cloned from another.

```php
Access::role('Manager')->cloneFrom('Admin')->except([
    Permission::RolesManage,
]);
```

This would make it easier to create slight variations without duplicating long permission arrays.

### 21.4.3 Role inheritance

Add optional role inheritance later, but keep it disabled by default.

```php
'roles' => [
    'Owner' => [
        'inherits' => ['Admin'],
        'permissions' => [Permission::RolesManage],
    ],

    'Admin' => [
        'inherits' => ['Member'],
        'permissions' => [Permission::UsersInvite],
    ],
]
```

This should be treated carefully because inheritance can make debugging harder.

### 21.4.4 Temporary role assignments

Support role assignments that expire.

```php
$user->in($company)->assignRole('Admin')->until(now()->addDays(7));
```

Use cases:

* Temporary support access.
* Trial admin access.
* Contractor access.
* Emergency access.

Database addition:

```text
expires_at nullable
```

### 21.4.5 Role assignment notes

Allow a note when assigning a role.

```php
$user->in($company)->assignRole('Admin', note: 'Promoted by owner during onboarding');
```

This would be useful for audit history.

## 21.5 Audit and safety additions

### 21.5.1 Access audit log

Add optional audit logging for access changes.

Events to log:

* Role assigned.
* Role removed.
* Permission given directly.
* Permission revoked.
* Role permissions changed.
* Global role assigned.
* Global role removed.

Example query:

```php
AccessAudit::for($user)->latest()->get();
```

### 21.5.2 Dangerous permission warnings

Allow permissions to be marked as dangerous.

```php
PermissionMeta::dangerous([
    Permission::RolesManage,
    Permission::BillingManage,
    Permission::SystemManage,
]);
```

Then the package can warn when these are assigned directly or given to broad roles.

### 21.5.3 Owner protection

Add optional rules to prevent locking yourself out.

Examples:

* A company must always have at least one Owner.
* The last Owner cannot remove their own Owner role.
* A user cannot demote themselves unless another Owner exists.

Config:

```php
'protection' => [
    'require_at_least_one_owner_per_scope' => true,
    'owner_role' => 'Owner',
]
```

### 21.5.4 Approval flow for sensitive changes

Optional future feature:

```php
AccessChange::request()
    ->for($user)
    ->in($company)
    ->assignRole('Admin')
    ->requiresApproval();
```

This is likely too much for the MVP, but useful in serious business apps.

## 21.6 UI and admin panel additions

### 21.6.1 Filament plugin

Create an optional Filament plugin for managing roles and permissions.

Features:

* Role list.
* Role editor.
* Permission matrix.
* User role assignments.
* Scoped role assignments.
* Audit log viewer.

This should live as a separate package:

```bash
composer require maxiviper117/laravel-access-filament
```

### 21.6.2 Inertia starter screens

Create optional starter pages for managing access in an Inertia app.

Screens:

* Role index.
* Role edit.
* Company members.
* Member role assignment.
* Permission matrix.

Frontend support:

* Svelte first.
* React later if needed.

### 21.6.3 Permission matrix component

Create a reusable permission matrix grouped by permission prefix.

Example groups:

```text
Users
- users.view
- users.invite
- users.manage

Roles
- roles.view
- roles.manage

Company
- company.update
```

This would make role editing much nicer.

## 21.7 Testing additions

### 21.7.1 Test helpers

Add fluent test helpers.

```php
Access::fake();

$this->actingAs($user);

$user->in($company)->assignRole('Owner');

$this->assertTrue($user->in($company)->can(Permission::UsersInvite));
```

### 21.7.2 Pest expectations

Add custom Pest expectations.

```php
expect($user)->toHavePermission(Permission::UsersInvite, in: $company);
expect($user)->not->toHavePermission(Permission::RolesManage, in: $company);
```

### 21.7.3 Factory helpers

Add model factory states.

```php
User::factory()
    ->withRole('Owner', in: $company)
    ->create();
```

### 21.7.4 Permission snapshot tests

Add a helper to snapshot the configured role map.

```php
expectAccessConfig()->toMatchSnapshot();
```

Useful for catching accidental permission changes in pull requests.

## 21.8 Import and migration additions

### 21.8.1 Spatie migration command

Add an optional migration command from Spatie Laravel Permission.

```bash
php artisan access:migrate-from-spatie
```

Supported migration data:

* Roles.
* Permissions.
* Role permissions.
* User role assignments.
* User direct permissions.
* Team-scoped assignments if teams were enabled.

This should be dry-run first by default.

```bash
php artisan access:migrate-from-spatie --dry-run
php artisan access:migrate-from-spatie --apply
```

### 21.8.2 Export access map

Add export command:

```bash
php artisan access:export
```

Output:

```json
{
  "roles": {
    "Owner": ["users.view", "users.invite"]
  }
}
```

Useful for audits, docs, and migrations.

### 21.8.3 Import access map

Add import command:

```bash
php artisan access:import access.json
```

Useful for syncing access rules across environments.

## 21.9 Documentation additions

### 21.9.1 Cookbook docs

Add example-driven docs for common scenarios.

Cookbook pages:

* Company owner, admin, member setup.
* Invite users to a company.
* Protect role management.
* Share permissions with Inertia.
* Use permissions in Svelte.
* Use permissions in Laravel Policies.
* Add platform admins.
* Prevent the last owner from being removed.
* Debug why a user cannot do something.

### 21.9.2 Authorization decision guide

Create a guide that explains where logic should live.

Example:

```text
Use permissions for broad abilities.
Use policies for object-specific rules.
Use middleware for simple route-level gates.
Use frontend access maps only for hiding UI, never for security.
```

### 21.9.3 Copy-paste starter setup

Add a single-page guide with the full recommended setup:

* Permission enum.
* Access config.
* User trait.
* Company policy.
* Inertia share middleware.
* Example route.
* Example controller.
* Example Svelte usage.

## 21.10 Advanced future additions

### 21.10.1 Wildcard permissions

Optional wildcard support:

```php
Permission::UsersAll = 'users.*';
```

This should be opt-in only because it can make debugging harder.

### 21.10.2 Permission groups

Allow metadata for grouping permissions in UIs.

```php
'groups' => [
    'Users' => [
        Permission::UsersView,
        Permission::UsersInvite,
        Permission::UsersManage,
    ],
]
```

### 21.10.3 Attribute-based permissions

Support PHP attributes for mapping policy methods to permissions.

```php
#[RequiresPermission(Permission::UsersInvite)]
public function inviteUsers(User $user, Company $company): bool
{
    return true;
}
```

This could reduce repetitive policy code, but should not be part of the MVP.

### 21.10.4 Access profiles

Support named access profiles for onboarding common roles.

```php
AccessProfile::companyDefault()->applyTo($company);
```

Useful if many companies should start with the same roles.

### 21.10.5 Multi-scope checks

Support checking permission across multiple scopes.

```php
$user->across($companies)->canAny(Permission::UsersManage);
$user->across($companies)->canAll(Permission::UsersView);
```

Useful for dashboards that aggregate multiple companies.

### 21.10.6 Access reports

Generate reports for auditing.

```bash
php artisan access:report --scope=company:1
```

Possible output:

* Users by role.
* Dangerous permissions.
* Users with direct permissions.
* Users with global roles.
* Expired temporary access.
* Companies without owners.

## 21.11 Recommended roadmap order

Suggested order after MVP:

1. `access:debug` and `access:explain` commands.
2. Inertia permission share helper.
3. TypeScript permission generator.
4. Policy helper trait.
5. Owner protection.
6. Audit log.
7. Test helpers and Pest expectations.
8. Permission matrix component.
9. Filament plugin.
10. Spatie migration command.

The best early additions are the ones that help you see and debug access decisions. Those will save the most time while keeping the package simple.

## 22. Success criteria

The package is successful if:

* You can explain it in under one minute.
* A new Laravel app can be configured in under ten minutes.
* You never need `setPermissionsTeamId()`.
* You rarely think about database pivot tables.
* You can read authorization code and immediately know which company/team it applies to.
* Policy code becomes simpler, not more confusing.
* Frontend permission sharing is easy.

## 23. Example final developer experience

```php
// Assign role
$user->in($company)->assignRole('Owner');

// Check permission
$user->in($company)->can(Permission::UsersInvite);

// Use in policy
public function inviteUsers(User $user, Company $company): bool
{
    return $user->in($company)->can(Permission::UsersInvite);
}

// Share with Inertia
'access' => Access::for($user)->in($company)->toArray([
    Permission::UsersInvite,
    Permission::RolesManage,
]);
```

This is the core of the package.

Everything else should exist only to support this flow.
