# Dynamic Roles and Permissions (Hybrid RBAC)

Laravel Access supports developer-defined permissions and roles, plus permissions and roles that an application creates at runtime.

Use backed enums for permissions that developers define in code. Use dynamic permission definitions when application administrators must add permission names at runtime. Dynamic role APIs let an application create roles and attach either kind of permission to them.

The package provides the database models and APIs. Your application provides the administration UI and authorizes who can create permissions, edit roles, and assign roles.

The model has three parts:

1. **Configured permissions**: Defined by developers, usually with backed enums listed in `permission_enums`.
2. **Dynamic permissions**: Defined at runtime by stable string names. Permission definitions are app-wide. Their grants can be direct or through scoped and global roles.
3. **Dynamic roles**: Created at runtime and stored in the database. A role can be scoped to a model instance or global to the application.

---

## 1. Role and Permission Ownership

The `access_roles` table stores role ownership and scope:

* `is_system`: `true` for configured roles and `false` for roles created at runtime.
* `is_global`: `true` for app-wide roles and `false` for scoped or shared non-global roles.
* `scope_type` and `scope_id`: Set when a role belongs to a specific model instance, such as one Workspace or Team.

The `access_permissions` table stores permission definitions for the whole application:

* Configured permissions are system-owned. `access:sync` marks them with `is_system = true`.
* Runtime definitions are dynamic. The package keeps them when `access:sync --prune` removes stale system-owned definitions.
* A permission definition does not grant access by itself. A direct assignment or a role attachment grants access in a scope or globally.

---

## 2. Creating a Custom Scoped Role

To allow a tenant administrator to create a custom role within their scope, use the scoped `createRole` method on the user context. The library automatically scopes the role, sets `is_system => false`, and sets `is_global => false`:

> [!NOTE]
> In these examples, `$scope` is a model instance from your application, such as a `Workspace`, `Team`, or `Company`.

```php:line-numbers
// Create the role within the specific scope using a string name
$role = $user->in($scope)->createRole('editor-assistant', 'Editor Assistant', 'Help with editing articles');
```

The role belongs to `$scope`. A custom global role has no scope. Create one with the global context:

```php:line-numbers
$role = $user->access()->createRole('support-agent', 'Support Agent');
```

You can also pass a `BackedEnum` as the role name. The enum's `value` is used as the role name:

```php:line-numbers
use App\Enums\CompanyRole;

$role = $user->in($scope)->createRole(CompanyRole::Admin, 'Company Admin');
```

---

## 3. Defining a Dynamic Permission

Use `Access::definePermission()` when an application administrator must create a permission at runtime and set its label or description. Use a stable string name. Permission definitions are app-wide, even when a role or direct grant is scoped.

```php:line-numbers
use Maxiviper117\Access\Facades\Access;

$permission = Access::definePermission(
    'invoices.export',
    'Export invoices',
    'Download invoice data.',
);
```

This call creates or updates the definition. It does not grant the permission. Permission strings passed to `givePermission()`, `giveGlobalPermission()`, `syncRolePermissions()`, or `addPermissionToRole()` can create a missing definition automatically. These methods do not set its label or description. Use `definePermission()` when the application needs that metadata.

If the name already belongs to a configured permission, `definePermission()` throws an exception. See [Define Permissions](/how-to/define-permissions) for naming, ownership, and removal details.

### Complete Scoped Example

This example defines a permission, adds it to a custom role, assigns the role, and checks access:

```php:line-numbers
use Maxiviper117\Access\Facades\Access;

$permission = Access::definePermission(
    'invoices.export',
    'Export invoices',
    'Download invoice data.',
);

$companyAdmin->in($scope)->createRole(
    'invoice-manager',
    'Invoice Manager',
);
$companyAdmin->in($scope)->addPermissionToRole(
    'invoice-manager',
    $permission->name,
);

$accountant->in($scope)->assignRole('invoice-manager');

$accountant->in($scope)->can('invoices.export'); // true
```

The application must authorize the administrator before it calls these APIs. The package does not decide which application users can manage permission definitions or roles.

The same app-wide permission can be attached to a global role or granted directly. For example, use `$user->access()->addPermissionToRole('support-agent', 'invoices.export')` for a global role, or `$user->giveGlobalPermission('invoices.export')` for a direct global grant.

---

## 4. Syncing and Editing Role Permissions

Use `syncRolePermissions()` to replace the full set of permissions attached to a role. Permission values can be backed enums or strings. Use enums for configured permissions and strings for dynamic definitions:

```php:line-numbers
use App\Enums\Permission;

$user->in($scope)->syncRolePermissions('editor-assistant', [
    Permission::PostCreate,
    Permission::PostEdit,
    'invoices.export',
]);
```

The string `'invoices.export'` can refer to a definition created with `Access::definePermission()`. If the definition does not exist, this API creates it with the name only.

You can also reference a role by its `BackedEnum` value when the role name matches an enum case:

```php:line-numbers
use App\Enums\CompanyRole;
use App\Enums\Permission;

$user->in($scope)->syncRolePermissions(CompanyRole::Admin, [
    Permission::CompanyMembersInvite,
    Permission::CompanySettingsManage,
]);
```

### Adding & Removing Individual Permissions

Use `addPermissionToRole()` and `removePermissionFromRole()` to change one permission without replacing the full list. These methods also accept dynamic permission names:

```php:line-numbers
use App\Enums\Permission;

// Add a configured permission
$user->in($scope)->addPermissionToRole('editor-assistant', Permission::PostCreate);

// Add or remove a dynamic permission
$user->in($scope)->addPermissionToRole('editor-assistant', 'invoices.export');
$user->in($scope)->removePermissionFromRole('editor-assistant', 'invoices.export');

```


---

## 5. Assigning and Checking Access

Checking user access with scoped roles is identical to checking static roles. Laravel Access resolves the mappings dynamically at runtime.

### Configured Permissions and Roles

Use backed enums for configured role names and permissions:

```php:line-numbers
use App\Enums\CompanyRole;

// Assign the role cleanly using the enum case
$user->in($scope)->assignRole(CompanyRole::Admin);

// Check role assignment
if ($user->in($scope)->hasRole(CompanyRole::Admin)) {
    // ...
}
```

### Dynamic Role Names and Permissions

Use string names for roles and permissions created at runtime:

```php:line-numbers
// Assign the dynamic role and check its assignment
$user->in($scope)->assignRole('invoice-manager');

if ($user->in($scope)->hasRole('invoice-manager')) {
    // ...
}

// Check a configured or dynamic permission
if ($user->in($scope)->can('invoices.export')) {
    // Allow the invoice export.
}
```

### Check Permissions in Policies

Check permissions instead of role names in policies and controllers. The role's grants can change without changing the permission check in application code:

```php:line-numbers
// Check a configured enum permission
if ($user->in($scope)->can(Permission::PostCreate)) {
    // Allow the action.
}
```

For a dynamic permission, pass its string name:

```php:line-numbers
if ($user->in($scope)->can('invoices.export')) {
    // Allow the invoice export.
}
```

---

## 6. Listing and Deleting Dynamic Roles

To retrieve the applicable roles inside the current scope (including both system roles and custom workspace-specific roles), call `roles()`:

```php:line-numbers
// Get all roles available in this scope
$roles = $user->in($scope)->roles();
```

To delete a role within the current scope:

```php:line-numbers
$user->in($scope)->deleteRole('editor-assistant');
```

You can also pass a `BackedEnum` for developer-defined roles:

```php:line-numbers
use App\Enums\CompanyRole;

$user->in($scope)->deleteRole(CompanyRole::Admin);
```


---

## 7. Protecting Role and Permission Administration

Use a configured permission, such as `Permission::RolesManage`, to protect endpoints that create permissions, edit roles, and assign roles. Keep this management permission in application code. A dynamic permission should not be the only permission used to authorize its own creation.

You can then write standard Laravel **Model Policies** to protect your role administration endpoints:

```php:line-numbers
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

        return $role->scope_type === $scope->getMorphClass()
            && (int) $role->scope_id === (int) $scope->getKey()
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

        return $role->scope_type === $scope->getMorphClass()
            && (int) $role->scope_id === (int) $scope->getKey()
            && $user->in($scope)->can(Permission::RolesManage);
    }
}
```

For the related scope and membership checks to apply in your other policies, see [Authorization Boundaries](/explanation/authorization-boundaries).

---

## 8. Coexistence with `access:sync`

Running the developer command `php artisan access:sync` keeps all developer-defined permissions and config roles in sync with your database.

It is designed to preserve runtime definitions:
* Configured roles and permissions are marked as `is_system = true`.
* When using `--prune`, the command only deletes stale roles and permissions marked as system-owned.
* Dynamic roles and permissions (`is_system = false`) remain in the database. Permission definitions are app-wide; their grants can be scoped or global.

Use [Dynamic Permission Definitions](/how-to/define-permissions) when app administrators need to add permission names at runtime. Existing dynamic-role APIs can also create a definition automatically when given a new string permission name.

---

## 9. Dynamic Role Actions (Action Pattern)

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

```php:line-numbers
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

```php:line-numbers
use App\Actions\Access\CreateRole;
use App\Enums\CompanyRole;

$role = CreateRole::run(
    name: CompanyRole::Admin,
    scope: $scope
);
```

#### Syncing Permissions to a Role

Use `SyncRolePermissions::run()` to replace the permissions attached to a custom role. The `role` argument accepts a `BackedEnum`, `string`, or `Role` model instance. The permission list accepts configured enums and dynamic strings:

```php:line-numbers
use App\Actions\Access\SyncRolePermissions;
use App\Enums\Permission;

SyncRolePermissions::run(
    role: 'editor-assistant',
    permissions: [
        Permission::PostCreate,
        Permission::PostEdit,
        'invoices.export',
    ],
    scope: $scope
);
```

#### Adding a Single Permission to a Role

Use `AddPermissionToRole::run()` to add a configured or dynamic permission to a role:

```php:line-numbers
use App\Actions\Access\AddPermissionToRole;
AddPermissionToRole::run(
    role: 'editor-assistant',
    permission: 'invoices.export',
    scope: $scope
);
```

#### Removing a Single Permission from a Role

Use `RemovePermissionFromRole::run()` to remove a single permission from a dynamic role:

```php:line-numbers
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

```php:line-numbers
use App\Actions\Access\DeleteRole;

DeleteRole::run('editor-assistant', $scope);
```
