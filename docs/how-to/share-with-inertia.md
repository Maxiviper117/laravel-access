---
title: Share Permissions With Inertia
---

# Share Permissions With Inertia

Build a small permission map for the active scope.

Share permissions close to the request context where you already know the current scope.

```php:line-numbers
use App\Enums\Permission;
use Maxiviper117\Access\Facades\Access;

'access' => fn () => $request->user() && $company
    ? Access::for($request->user())->in($company)->toArray([
        Permission::UsersInvite,
        Permission::RolesManage,
        Permission::CompanyUpdate,
    ])
    : [],
```

The output is keyed by permission value:

```php:line-numbers
[
    'users.invite' => true,
    'roles.manage' => false,
    'company.update' => true,
]
```

Use this only to shape the interface. Server-side policy checks still protect the action.

## Svelte Example

```svelte:line-numbers
{#if $page.props.access['users.invite']}
    <button>Invite user</button>
{/if}
```

## React Example

```tsx:line-numbers
const canInvite = page.props.access['users.invite'];

return canInvite ? <button>Invite user</button> : null;
```

## Keep Maps Small

Avoid sharing every permission in the system by default. Share the permissions the current page needs:

```php:line-numbers
Access::for($user)->in($company)->toArray([
    Permission::UsersInvite,
    Permission::RolesManage,
]);
```
