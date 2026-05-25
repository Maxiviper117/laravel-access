---
title: Caching
---

# Caching

Permission lookups can be cached per actor and scope.

The cache key includes:

- actor type
- actor ID
- scope type
- scope ID
- package cache version

Assignments clear their own actor/scope cache when they change. `access:sync` and `access:clear` advance the package cache version, which invalidates old entries without flushing the whole application cache.

Caching is disabled by default in the testing environment.

## Invalidation

The package invalidates relevant entries when assignments change. Full sync and clear commands advance the package cache version:

```bash
php artisan access:sync
php artisan access:clear
```

That makes old keys unreachable without flushing unrelated application cache entries.

## When to Disable Cache

Disable cache while debugging authorization behavior:

```php:line-numbers
'cache' => [
    'enabled' => false,
],
```

Leave it disabled in tests so assertions always read current database state.
