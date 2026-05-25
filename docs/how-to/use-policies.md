---
title: Use Policies
---

# Use Policies

Use Laravel policies for object-specific authorization and call Laravel Access inside the policy method.

This keeps controllers simple and keeps access decisions close to the model being protected.

```php:line-numbers
namespace App\Policies;

use App\Enums\Permission;
use App\Models\Company;
use App\Models\User;

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
}
```

Controllers stay Laravel-native:

```php:line-numbers
$this->authorize('inviteUsers', $company);
```

## Register the Policy

Register policies the same way you normally would in Laravel:

```php:line-numbers
use App\Models\Company;
use App\Policies\CompanyPolicy;

Gate::policy(Company::class, CompanyPolicy::class);
```

## Combine Access With Object Rules

Policies can include both permission checks and object-specific checks:

```php:line-numbers
public function update(User $user, Company $company): bool
{
    return $company->isActive()
        && $user->in($company)->can(Permission::CompanyUpdate);
}
```

The permission answers whether the user has the broad ability. The policy can still decide whether that ability applies to this particular model state.
