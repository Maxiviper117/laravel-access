---
title: Middleware
---

# Middleware

The service provider registers the `access` middleware alias.

```php
Route::post('/companies/{company}/users/invite', InviteUserController::class)
    ->middleware('access:users.invite,company');
```

The first argument is the permission name. The second argument is the route parameter that should be used as the scope.

Policies are still recommended for object-specific authorization. Middleware is useful for simple route-level checks.

## Signature

```text
access:{permission},{routeParameter}
```

Examples:

```php
->middleware('access:roles.manage,company')
->middleware('access:projects.update,project')
```

The route parameter must resolve to an Eloquent model:

```php
Route::patch('/companies/{company}', UpdateCompanyController::class)
    ->middleware('access:company.update,company');
```

If there is no authenticated user, no scope model, or the user lacks the permission, the middleware aborts with `403`.
