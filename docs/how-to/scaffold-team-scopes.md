---
title: Scaffold Team Scopes
---

# Scaffold Team Scopes

::: warning Laravel Starter Kit Team Support
Do not use `access:scope` if you already enabled team support in the official Laravel starter kit (`laravel new --teams`). That starter kit generates its own membership, invitation, and scope-switching code. `access:scope` assumes a fresh app without those files and will conflict with or duplicate the starter kit's structure.
:::

Use `access:scope` when your app needs team-style membership, invitations, current scope switching, and scoped route URLs.

The generated code is first-party application code. Laravel Access still only answers authorization questions like:

```php:line-numbers
$user->in($company)->assignRole(CompanyRole::Admin);
$user->in($company)->can(Permission::CompanyMembersInvite);
```

When the scaffold generates a role enum, prefer using direct enum instances instead of repeating raw role strings:

```php:line-numbers
$user->in($company)->assignRole(CompanyRole::Admin);
```

The generated membership pivot and invitation model both cast `role` to the generated enum. For `--name=company`, that means `Membership::$role`, `CompanyInvitation::$role`, and `$user->companyRole($company)` use `CompanyRole`.

## Choose a Name

Run the command interactively:

```bash
php artisan access:scope
```

Or pass the domain term directly:

```bash
php artisan access:scope --name=company
php artisan access:scope --name=workspace
php artisan access:scope --name=tenant
```

Interactive mode also asks which invitation UI should be generated. Choose `blade`, `react`, `vue`, or `svelte`. For non-interactive setup, pass `--frontend`:

```bash
php artisan access:scope --name=company --frontend=blade
php artisan access:scope --name=company --frontend=react
php artisan access:scope --name=company --frontend=vue
php artisan access:scope --name=company --frontend=svelte
```

Interactive mode also asks whether to generate invitation mail helpers. Pass `--notifications` in non-interactive runs to generate the mail notification and controller helper methods:

```bash
php artisan access:scope --name=company --notifications
```

The selected name drives every generated table, class, relationship, middleware, and route parameter.

| Concept                   | `team`                 | `company`                 |
| ------------------------- | ---------------------- | ------------------------- |
| Scope table               | `teams`                | `companies`               |
| Members table             | `team_members`         | `company_members`         |
| Invitations table         | `team_invitations`     | `company_invitations`     |
| User current scope column | `current_team_id`      | `current_company_id`      |
| Scope model               | `Team`                 | `Company`                 |
| Invitation model          | `TeamInvitation`       | `CompanyInvitation`       |
| Concern                   | `HasTeams`             | `HasCompanies`            |
| Middleware                | `EnsureTeamMembership` | `EnsureCompanyMembership` |
| Role enum                 | `TeamRole`             | `CompanyRole`             |
| Permission cases          | `TeamMembersInvite`    | `CompanyMembersInvite`    |
| Current route parameter   | `{current_team}`       | `{current_company}`       |

For irregular words, pass overrides:

```bash
php artisan access:scope --name=alumnus --plural=alumni
```

## Generated Files

For `--name=company`, the command publishes:

```text:line-numbers
database/migrations/*_create_companies_table.php
database/migrations/*_create_company_members_table.php
database/migrations/*_create_company_invitations_table.php
database/migrations/*_add_current_company_id_to_users_table.php
app/Models/Company.php
app/Models/Membership.php
app/Models/CompanyInvitation.php
app/Concerns/HasCompanies.php
app/Http/Middleware/EnsureCompanyMembership.php
app/Enums/CompanyRole.php
app/Http/Controllers/Auth/CompanyInvitationController.php
routes/company-invitations.php
resources/views/auth/company-invitation-error.blade.php
resources/views/auth/company-invited-register.blade.php
```

With `--notifications`, it also publishes:

```text:line-numbers
app/Notifications/CompanyInvitationNotification.php
```

If you choose an Inertia frontend, the Blade files are replaced with starter pages:

```text:line-numbers
resources/js/Pages/auth/CompanyInvitationError.tsx
resources/js/Pages/auth/CompanyInvitedRegister.tsx
```

For Vue the extension is `.vue`; for Svelte the extension is `.svelte`.

It also updates:

```text:line-numbers
config/access.php
app/Enums/Permission.php
app/Models/User.php
app/Providers/AppServiceProvider.php
bootstrap/app.php
```

The `bootstrap/app.php` update registers both the generated middleware alias and the generated invitation route file:

```php:line-numbers
->withRouting(
    web: __DIR__.'/../routes/web.php',
    commands: __DIR__.'/../routes/console.php',
    health: '/up',
    then: function (): void {
        \Illuminate\Support\Facades\Route::middleware('web')
            ->group(base_path('routes/company-invitations.php'));
    },
)
```

Pass `--no-concern` if you do not want the command to patch the `User` model.

## Invitation UI

The generated invitation UI is basic Tailwind starter code. Most apps should customize it to match their auth layout, starter kit, validation styling, and design system.

Blade output uses:

```text:line-numbers
resources/views/auth/company-invitation-error.blade.php
resources/views/auth/company-invited-register.blade.php
```

Inertia output uses:

```text:line-numbers
resources/js/Pages/auth/CompanyInvitationError.{tsx,vue,svelte}
resources/js/Pages/auth/CompanyInvitedRegister.{tsx,vue,svelte}
```

The Inertia pages are generated for Inertia v3 and use the current `<Form>` component from `@inertiajs/react`, `@inertiajs/vue3`, or `@inertiajs/svelte`.

The invited registration page receives the invitation email and the scope name. For `--name=company`, Inertia pages receive `invitation.companyName`; Blade views can read `$invitation->company->name`.

## Use It Effectively With Laravel Access

`access:scope` is meant to remove the repetitive team setup work: migrations, relationships, invitation plumbing, current scope switching, middleware, and URL defaults. After it runs, the remaining work should mostly be configuration and light application wiring.

For a typical company-scoped app, the fast path is:

```bash
php artisan access:install --enum
php artisan access:scope --name=company
php artisan migrate
```

Then configure your permissions and roles:

```php:line-numbers
// app/Enums/Permission.php
enum Permission: string
{
    case CompanyMembersView = 'company.members.view';
    case CompanyMembersInvite = 'company.members.invite';
    case CompanyMembersManage = 'company.members.manage';
    case CompanySettingsManage = 'company.settings.manage';
    case CompanyUpdate = 'company.update';
}
```

```php:line-numbers
// config/access.php
use App\Enums\CompanyRole;

'permission_enums' => [
    App\Enums\Permission::class,
],
'default_scope_model' => App\Models\Company::class,

'roles' => [
    CompanyRole::Owner->value => [
        Permission::CompanyMembersView,
        Permission::CompanyMembersInvite,
        Permission::CompanyMembersManage,
        Permission::CompanySettingsManage,
        Permission::CompanyUpdate,
    ],
    CompanyRole::Admin->value => [
        Permission::CompanyMembersView,
        Permission::CompanyMembersInvite,
    ],
    CompanyRole::Member->value => [
        Permission::CompanyMembersView,
    ],
],
```

`access:scope` writes the generated model into `config/access.php`:

```php:line-numbers
'default_scope_model' => App\Models\Company::class,

'teams' => [
    'model' => App\Models\Company::class,
    'singular' => 'company',
    'plural' => 'companies',
],
```

When `app/Enums/Permission.php` exists, `access:scope` also appends starter cases like `CompanyMembersInvite` to that enum. For modular apps, you can create additional backed enums manually and list them in `permission_enums`.

`default_scope_model` is not a table name. It points to the Eloquent model Laravel Access should use when developer commands need to resolve a scope string, such as `access:debug --scope=company:acme-corp`. The table comes from that model.

Sync those role definitions into the database:

```bash
php artisan access:sync
```

From there, use the generated membership layer for "does this user belong to this company?" and Laravel Access for "what may this user do inside this company?":

```php:line-numbers
$company->users()->attach($user, ['role' => CompanyRole::Admin->value]);

$user->in($company)->assignRole(CompanyRole::Admin);

if ($user->in($company)->can(Permission::CompanyMembersInvite)) {
    // Show invite action or allow invite endpoint.
}
```

For route protection, combine the generated scope membership middleware with Laravel Access permissions:

```php:line-numbers
Route::middleware(['auth', 'company'])
    ->prefix('{current_company}')
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');

        Route::post('members/invite', InviteCompanyMemberController::class)
            ->middleware('access:users.invite,current_company')
            ->name('members.invite');
    });
```

That gives you two explicit checks:

- `company` proves the current route company belongs to the authenticated user and updates `current_company_id`.
- `access:users.invite,current_company` proves the user has the permission in that same company scope.

## Membership Tables vs Access Tables

The tables generated by `access:scope` are not replacements for the core Laravel Access tables. They store different kinds of state.

Generated scope tables store app membership state:

```text:line-numbers
companies
company_members
company_invitations
users.current_company_id
```

They answer questions like:

- Does this user belong to this company?
- Which company is currently active?
- Which email was invited?
- What membership role was attached to the invitation?

Laravel Access tables store authorization state:

```text:line-numbers
access_permissions
access_roles
access_role_permissions
access_assignments
```

They answer questions like:

- Which permissions exist?
- Which permissions belong to the `Admin` role?
- Which role or direct permission does this user have in this company?
- Can this user perform this action in this company?

That is why setup often has two writes:

```php:line-numbers
$company->users()->attach($user, ['role' => CompanyRole::Admin->value]);

$user->in($company)->assignRole(CompanyRole::Admin);
```

The first line makes the user a company member. The second line gives the user Laravel Access permissions inside that company.

You can keep membership roles and access roles in sync, or let them diverge. For most apps, using the same enum values (`CompanyRole::Owner->value`, `CompanyRole::Admin->value`, `CompanyRole::Member->value`) keeps the model simple and avoids string drift.

For policies, keep the same pattern:

```php:line-numbers
public function invite(User $user, Company $company): bool
{
    return $user->belongsToCompany($company)
        && $user->in($company)->can(Permission::CompanyMembersInvite);
}
```

The main manual wiring left after scaffolding is usually:

- Adjust the generated Blade views or Inertia pages to match your starter kit and frontend stack.
- Add your own UI/controllers for creating companies and sending invitations.
- Decide whether membership role names should mirror Laravel Access role names.

## Database Shape

The generated membership layer is intentionally simple:

```text:line-numbers
users
  current_company_id

companies
  id
  name
  slug
  deleted_at

company_members
  company_id
  user_id
  role

company_invitations
  company_id
  email
  role
  code
  expires_at
  accepted_at
```

The scope model uses soft deletes and route model binding by `slug`.

The `role` columns are strings in the database, but generated models cast them to the generated role enum:

```php:line-numbers
$membership->role === CompanyRole::Admin;
$invitation->role === CompanyRole::Admin;
$user->companyRole($company) === CompanyRole::Admin;
```

## Routes and Current Scope

Generated scoped routes can use a current scope parameter:

```php:line-numbers
Route::middleware(['auth', 'company'])->prefix('{current_company}')->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
});
```

When the request contains `{current_company}`, `EnsureCompanyMembership` verifies that the authenticated user belongs to the company. If the route company is not already the user's current company, it switches `current_company_id`.

The generated `AppServiceProvider` URL defaults let Laravel inject the current company slug into named routes:

```php:line-numbers
route('dashboard');
```

## Invitations

The scaffold includes an invitation flow for existing and brand-new users:

```php:line-numbers
Route::get('invitations/{invitation:code}', [CompanyInvitationController::class, 'show']);
Route::post('invitations/{invitation:code}/accept', [CompanyInvitationController::class, 'accept']);
Route::get('invitations/{invitation:code}/register', [CompanyInvitationController::class, 'registerForm']);
Route::post('invitations/{invitation:code}/register', [CompanyInvitationController::class, 'register']);
```

If no user exists for the invited email, the invitee is sent to the dedicated invited registration page. The email comes from the invitation and is locked. After registration, the user is attached to the scope, the invitation is marked accepted, the user is logged in, and the app redirects to the configured route.

Configure this behavior in `config/access.php`:

```php:line-numbers
'invitations' => [
    'require_existing_user' => false,
    'expiry_days' => 7,
    'redirect_after_accept' => 'dashboard',
],
```

Expired or already accepted invitation codes render the generated error view with a `410` response.

To also scaffold generic sending helpers, run:

```bash
php artisan access:scope --name=company --notifications
```

That adds `CompanyInvitationNotification`, a `store` method for creating invitations, a private `sendInvitation` helper, and a protected route like:

```php:line-numbers
Route::post('{company:slug}/invitations', [CompanyInvitationController::class, 'store'])
    ->middleware(['auth', 'company:Admin'])
    ->name('company.invitations.store');
```

The notification sends a link to `company.invitations.show`. Customize the notification copy, route protection, and validation rules before exposing it in production.

## Sync Access Roles

The generated membership role, such as `CompanyRole::Admin`, is the membership role stored on `company_members`. Laravel Access roles still come from `config/access.php` and are synced with:

```bash
php artisan access:sync
```

Keep the names aligned when you want membership roles and authorization roles to match:

```php:line-numbers
use App\Enums\CompanyRole;

'roles' => [
    CompanyRole::Owner->value => [Permission::CompanyMembersManage],
    CompanyRole::Admin->value => [Permission::CompanyMembersInvite],
    CompanyRole::Member->value => [Permission::CompanyMembersView],
],
```

Then assign access in the generated scope:

```php:line-numbers
$user->in($company)->assignRole(CompanyRole::Admin);
```
