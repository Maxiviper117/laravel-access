---
title: Policies
---

# Policies

Laravel Access does not replace Laravel policies. It gives policies a database-backed way to answer permission questions.

Use this package inside policies:

```php:line-numbers
public function update(User $user, Company $company): bool
{
    return $user->in($company)->can(Permission::CompanyUpdate);
}
```

Use Laravel authorization from controllers:

```php:line-numbers
$this->authorize('update', $company);
```

This keeps route and controller code clean while keeping object-specific rules in the policy layer.

## Where Logic Belongs

Use permissions for broad abilities:

```php:line-numbers
Permission::UsersInvite
Permission::RolesManage
Permission::CompanyUpdate
```

Use policies for object-specific rules:

```php:line-numbers
return $company->isActive()
    && $user->in($company)->can(Permission::UsersInvite);
```

Use middleware for simple route-level checks where the route parameter is enough:

```php:line-numbers
->middleware('access:users.invite,company')
```

Use frontend permission maps only to shape the interface.
