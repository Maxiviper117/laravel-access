# Dynamic Roles (Hybrid RBAC)

Laravel Access uses an **Enum-First, Code-Driven** paradigm. Developers define permissions strictly in code using PHP Backed Enums. This guarantees type-safety, IDE autocomplete, and clean static analysis.

However, in many B2B or SaaS applications, you need to allow **end-users (e.g. Workspace Admins) to create custom roles** (like "Assistant Manager" or "Night Shift Staff") and choose which permissions those roles have.

Laravel Access handles this elegantly using a **Hybrid RBAC Model**:
1. **Static Permissions (Enums)**: Defined by developers in PHP code.
2. **Dynamic Roles (Database)**: Created and customized by end-users at runtime.

---

## 1. The Dynamic Roles Schema

To support user-created roles, your `access_roles` table includes fields to track whether a role is developer-defined or user-defined, and what tenant/scope model it belongs to:

* `is_system` (boolean): `true` for system-wide roles defined in `config/access.php`, `false` for user-created custom roles.
* `scope_type` / `scope_id`: Populated if a role is created specifically within a scoped tenant model (e.g., a specific Workspace or Team).

---

## 2. Creating a Custom Scoped Role

To allow a tenant administrator to create a custom role within their scope, use the scoped `createRole` method on the user context. The library automatically scopes the role, sets `is_system => false`, and sets `is_global => false`:

> [!NOTE]
> In all of the examples below, the `$scope` variable refers to a concrete instance of your application's scoped tenant model (for example, a `Workspace`, `Team`, or `Company` instance).

```php
// Create the role within the specific scope using a string name
$role = $user->in($scope)->createRole('editor-assistant', 'Editor Assistant', 'Help with editing articles');
```

You can also pass a `BackedEnum` as the role name. The enum's `value` is used as the role name:

```php
use App\Enums\CompanyRole;

$role = $user->in($scope)->createRole(CompanyRole::Admin, 'Company Admin');
```

---

## 3. Syncing & Editing Permissions of a Scoped Role

Present your tenant administrators with a checkbox list of the static **System Permissions** defined in your enum. When they save their role, sync the pivot mappings using `syncRolePermissions`. 

While standard strings are fully supported, using a PHP Backed Enum is highly recommended:

```php
use App\Enums\Permission;

$user->in($scope)->syncRolePermissions('editor-assistant', [
    Permission::PostCreate,
    Permission::PostEdit,
]);
```

You can also reference a role by its `BackedEnum` value when the role name matches an enum case:

```php
use App\Enums\CompanyRole;
use App\Enums\Permission;

$user->in($scope)->syncRolePermissions(CompanyRole::Admin, [
    Permission::CompanyMembersInvite,
    Permission::CompanySettingsManage,
]);
```

### Adding & Removing Individual Permissions

Alternatively, if you want to add or remove individual permissions from a role dynamically without replacing the entire list, use `addPermissionToRole` and `removePermissionFromRole`:

```php
use App\Enums\Permission;

// Add a single permission to a dynamic role
$user->in($scope)->addPermissionToRole('editor-assistant', Permission::PostCreate);

// Remove a single permission from a dynamic role
$user->in($scope)->removePermissionFromRole('editor-assistant', Permission::PostCreate);
```


---

## 4. Assigning and Checking Access

Checking user access with scoped roles is identical to checking static roles. Laravel Access resolves the mappings dynamically at runtime.

### Recommended: Backed Enums

For all developer-defined roles, using PHP Backed Enums is highly recommended for IDE autocompletion, type safety, and clean static analysis:

```php
use App\Enums\CompanyRole;

// Assign the role cleanly using the enum case
$user->in($scope)->assignRole(CompanyRole::Admin);

// Check role assignment
if ($user->in($scope)->hasRole(CompanyRole::Admin)) {
    // ...
}
```

### Supported: Strings for Runtime User-Created Roles

If a custom role is created dynamically by an end-user at runtime (where no compile-time PHP enum exists), you can pass its standard string name instead:

```php
// Assign the dynamically created role by string name
$user->in($scope)->assignRole('custom-role-name');

// Check the dynamic role assignment
if ($user->in($scope)->hasRole('custom-role-name')) {
    // ...
}
```

### Checking Permissions (Recommended)

In B2B apps, you should check permissions instead of roles in your policies and controllers. This ensures that even if a tenant admin changes the permissions of the `editor-assistant` role, your authorization logic remains 100% correct:

```php
// Check the permission (automatically resolves through the custom role mapping)
if ($user->in($scope)->can(Permission::PostCreate)) {
    // Authorized!
}
```

---

## 5. Listing and Deleting Dynamic Roles

To retrieve the applicable roles inside the current scope (including both system roles and custom workspace-specific roles), call `roles()`:

```php
// Get all roles available in this scope
$roles = $user->in($scope)->roles();
```

To delete a role within the current scope:

```php
$user->in($scope)->deleteRole('editor-assistant');
```

You can also pass a `BackedEnum` for developer-defined roles:

```php
use App\Enums\CompanyRole;

$user->in($scope)->deleteRole(CompanyRole::Admin);
```


---

## 6. Protecting Dynamic Role Administration

To authorize users to create/edit roles, you should define a static **meta-permission** in your enum, e.g. `Permission::RolesManage`.

You can then write standard Laravel **Model Policies** to protect your role administration endpoints:

```php
namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;
use Maxiviper117\Access\Models\Role;

class RolePolicy
{
    /**
     * Can a user create a custom role?
     */
    public function create(User $user, $scope): bool
    {
        return $user->in($scope)->can(Permission::RolesManage);
    }

    /**
     * Can a user edit a custom role?
     */
    public function update(User $user, Role $role, $scope): bool
    {
        // System-wide developer-defined roles cannot be edited by users
        if ($role->is_system) {
            return false;
        }

        return $role->scope_id === $scope->getKey()
            && $user->in($scope)->can(Permission::RolesManage);
    }

    /**
     * Can a user delete a custom role?
     */
    public function delete(User $user, Role $role, $scope): bool
    {
        if ($role->is_system) {
            return false;
        }

        return $role->scope_id === $scope->getKey()
            && $user->in($scope)->can(Permission::RolesManage);
    }
}
```

---

## 7. Coexistence with `access:sync`

Running the developer command `php artisan access:sync` keeps all developer-defined permissions and config roles in sync with your database. 

It is designed to be **safe for dynamic roles**:
* Configured system roles are synced and marked as `is_system = true`.
* When using the `--prune` option, the sync command **only prunes developer-defined roles** (where `is_system = true`).
* All dynamic, user-created roles (`is_system = false`) are left completely untouched in the database.

---

## 8. Dynamic Role Actions (Action Pattern)

When you run `php artisan access:install`, Laravel Access automatically scaffolds five reusable, standalone Action classes in your project under the `App\Actions\Access` namespace:
* `CreateRole`
* `SyncRolePermissions`
* `AddPermissionToRole`
* `RemovePermissionFromRole`
* `DeleteRole`

These classes follow the **Single Responsibility Principle (SRP)**, encapsulating all dynamic role validation, database writes, and cache invalidation. 

> [!TIP]
> **Native Backed Enum & String Support**
> Just like the core `AccessContext` APIs, all scaffolded Action stubs natively support passing a `BackedEnum`, `string`, or Eloquent `Role` instance as the role identifier. The actions resolve the correct database role record for the active scope and require any `Role` model instance to already belong to that scope.

### Using the Actions

#### Creating a Role

Use `CreateRole::run()` to create custom dynamic roles. You can pass a `BackedEnum` or `string` for the name. If `label` is omitted, it will be automatically converted to a developer-friendly headline string (e.g. `'editor-assistant'` becomes `Editor Assistant`):

```php
use App\Actions\Access\CreateRole;

// Create a scoped custom role using a string name
$role = CreateRole::run(
    name: 'editor-assistant',
    description: 'Help with editing articles',
    scope: $scope
);

// Create a global custom role by string
$role = CreateRole::run(
    name: 'support-agent',
    label: 'Support Agent',
    description: 'Global support role'
);
```

You can also pass a `BackedEnum` for the role name. The enum's `value` is used:

```php
use App\Actions\Access\CreateRole;
use App\Enums\CompanyRole;

$role = CreateRole::run(
    name: CompanyRole::Admin,
    scope: $scope
);
```

#### Syncing Permissions to a Role

Use `SyncRolePermissions::run()` to map developer-defined enum permissions onto custom roles. The `role` argument accepts a `BackedEnum`, `string`, or `Role` model instance:

```php
use App\Actions\Access\SyncRolePermissions;
use App\Enums\Permission;

SyncRolePermissions::run(
    role: 'editor-assistant',
    permissions: [
        Permission::PostCreate,
        Permission::PostEdit,
    ],
    scope: $scope
);
```

#### Adding a Single Permission to a Role

Use `AddPermissionToRole::run()` to add a single permission to a dynamic role:

```php
use App\Actions\Access\AddPermissionToRole;
use App\Enums\Permission;

AddPermissionToRole::run(
    role: 'editor-assistant',
    permission: Permission::PostCreate,
    scope: $scope
);
```

#### Removing a Single Permission from a Role

Use `RemovePermissionFromRole::run()` to remove a single permission from a dynamic role:

```php
use App\Actions\Access\RemovePermissionFromRole;
use App\Enums\Permission;

RemovePermissionFromRole::run(
    role: 'editor-assistant',
    permission: Permission::PostCreate,
    scope: $scope
);
```

#### Deleting a Role

Use `DeleteRole::run()` to safely delete dynamic roles. This action accepts a `BackedEnum`, `string`, or `Role` model instance:

```php
use App\Actions\Access\DeleteRole;

DeleteRole::run('editor-assistant', $scope);
```
