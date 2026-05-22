---
title: Complete Example
---

# Complete Example: Project Management SaaS

This example walks through a Laravel app that uses `access:scope` for company membership and Laravel Access for scoped authorization.

## Scenario

A project management SaaS where:

- Users belong to companies
- Companies have projects and tasks
- Membership is tracked in generated `company_members`
- Authorization roles are scoped per company
- Policies decide whether a user can view, create, update, or delete project data

## Models

| Model | Purpose | Source |
|---|---|---|
| `User` | Authenticated actor using `HasAccess` and generated `HasCompanies` | App + scaffold |
| `Company` | Scope model, route-bound by slug | `access:scope` |
| `Membership` | Company membership pivot with enum-cast role | `access:scope` |
| `CompanyInvitation` | Invitation model with code, expiry, acceptance | `access:scope` |
| `Project` | Belongs to company | You create |
| `Task` | Belongs to project | You create |

## 1. Install and Scaffold Company Scopes

```bash
composer require maxiviper117/laravel-access
php artisan access:install --enum
php artisan access:scope --name=company
php artisan migrate
```

Running `access:scope` without flags shows interactive prompts that guide you through scope naming, invitation UI stack, notification helpers, and a confirmation summary before generating files:

```
php artisan access:scope

 What should the group be called? [team]:
 > company

 Which invitation UI should be generated? [blade]:
  [0] blade
  [1] react
  [2] vue
  [3] svelte
 > 3

 Generate invitation email notification helpers? (yes/no) [yes]:
 >

Singular: company  |  Plural: companies  |  Table: companies

 Confirm? (yes/no) [yes]:
 >
```

For non-interactive setup, pass flags directly:

```bash
php artisan access:scope --name=company --frontend=react
php artisan access:scope --name=company --frontend=vue
php artisan access:scope --name=company --frontend=svelte
```

To scaffold a starter mail notification and invitation creation endpoint as well, combine the options you need:

```bash
php artisan access:scope --name=company --notifications
php artisan access:scope --name=company --frontend=react --notifications
```

`access:install --enum` publishes the package config and migrations, generates `app/Enums/Permission.php`, adds it to `permission_enums`, and scaffolds five reusable Action classes under `app/Actions/Access/` for dynamic role management.

`access:scope --name=company` keeps `Permission.php` as the canonical permission enum and appends starter company permission cases when that file exists.

The command generates the company membership layer:

```text
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
```

It also generates basic Tailwind starter invitation error and invited-registration UI. By default those are Blade views; with `--frontend=react`, `--frontend=vue`, or `--frontend=svelte`, they are Inertia pages under `resources/js/Pages/auth`.

For a company app, the generated UI files are:

```text
resources/views/auth/company-invitation-error.blade.php
resources/views/auth/company-invited-register.blade.php
```

Or, for Inertia:

```text
resources/js/Pages/auth/CompanyInvitationError.{tsx,vue,svelte}
resources/js/Pages/auth/CompanyInvitedRegister.{tsx,vue,svelte}
```

The invited-registration UI receives the company name (`$invitation->company->name` in Blade or `invitation.companyName` in Inertia) so the page can tell invitees which company they are joining.

Treat these files as starter UI. They use generic Tailwind classes and should be adjusted to match your auth layout, form components, error display, and frontend conventions.

It also patches `config/access.php`, `app/Models/User.php`, `app/Providers/AppServiceProvider.php`, and `bootstrap/app.php`.

Add `HasAccess` to `User`. The generated `HasCompanies` trait is added by the scaffold unless you passed `--no-concern`.

```php
use App\Concerns\HasCompanies;
use Maxiviper117\Access\Concerns\HasAccess;

class User extends Authenticatable
{
    use HasAccess;
    use HasCompanies;
}
```

## 2. Define Permissions

Edit `app/Enums/Permission.php`:

```php
namespace App\Enums;

enum Permission: string
{
    case ProjectsView = 'projects.view';
    case ProjectsCreate = 'projects.create';
    case ProjectsUpdate = 'projects.update';
    case ProjectsDelete = 'projects.delete';
    case TasksView = 'tasks.view';
    case TasksCreate = 'tasks.create';
    case TasksUpdate = 'tasks.update';
    case TasksDelete = 'tasks.delete';
    case CompanyMembersInvite = 'company.members.invite';
    case CompanyMembersManage = 'company.members.manage';
    case BillingView = 'billing.view';
    case CompanyUpdate = 'company.update';
    case SystemManage = 'system.manage';
}
```

## 3. Configure Roles

`access:scope` already points `default_scope_model` to `Company::class`. Keep `Permission.php` listed in `permission_enums`, then configure scoped roles. Use generated `CompanyRole` enum values so membership roles and access roles stay aligned.

```php
use App\Enums\CompanyRole;
use App\Enums\Permission;
use App\Models\Company;

return [
    'user_model' => 'App\\Models\\User',

    'permission_enums' => [
        Permission::class,
    ],

    'default_scope_model' => Company::class,

    'teams' => [
        'model' => Company::class,
        'singular' => 'company',
        'plural' => 'companies',
    ],

    'roles' => [
        CompanyRole::Owner->value => [
            Permission::ProjectsView,
            Permission::ProjectsCreate,
            Permission::ProjectsUpdate,
            Permission::ProjectsDelete,
            Permission::TasksView,
            Permission::TasksCreate,
            Permission::TasksUpdate,
            Permission::TasksDelete,
            Permission::CompanyMembersInvite,
            Permission::CompanyMembersManage,
            Permission::BillingView,
            Permission::CompanyUpdate,
        ],
        CompanyRole::Admin->value => [
            Permission::ProjectsView,
            Permission::ProjectsCreate,
            Permission::ProjectsUpdate,
            Permission::ProjectsDelete,
            Permission::TasksView,
            Permission::TasksCreate,
            Permission::TasksUpdate,
            Permission::TasksDelete,
            Permission::CompanyMembersInvite,
            Permission::BillingView,
        ],
        CompanyRole::Member->value => [
            Permission::ProjectsView,
            Permission::TasksView,
            Permission::TasksCreate,
            Permission::TasksUpdate,
        ],
    ],

    'global_roles' => [
        'Platform Admin' => [
            Permission::SystemManage,
        ],
    ],
];
```

## 4. Add Project and Task Tables

The company and membership tables are already generated. Add only the app-specific project and task tables.

```php
// database/migrations/xxxx_xx_xx_000001_create_projects_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('status')->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
```

```php
// database/migrations/xxxx_xx_xx_000002_create_tasks_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('todo');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
```

Run migrations and sync configured access roles:

```bash
php artisan migrate
php artisan access:sync
```

`access:sync` creates and updates the role and permission definitions. It does not assign those roles to users. The scoped assignments happen later with `$user->in($company)->assignRole(...)`, usually in seeders, invitation acceptance, or your member-management UI.

## 5. Add Project and Task Models

Add the domain relationships to the generated `Company` model:

```php
// app/Models/Company.php
use Illuminate\Database\Eloquent\Relations\HasMany;

public function projects(): HasMany
{
    return $this->hasMany(Project::class);
}
```

Create `Project`:

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use HasFactory;

    protected $fillable = ['company_id', 'name', 'status'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }
}
```

Create `Task`:

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    use HasFactory;

    protected $fillable = ['project_id', 'title', 'assigned_to', 'status'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
```

## 6. Write Policies

Policies combine object rules with Laravel Access permission checks.

```php
namespace App\Policies;

use App\Enums\Permission;
use App\Models\Company;
use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function viewAny(User $user, Company $company): bool
    {
        return $user->belongsToCompany($company)
            && $user->in($company)->can(Permission::ProjectsView);
    }

    public function view(User $user, Project $project): bool
    {
        return $user->belongsToCompany($project->company)
            && $user->in($project->company)->can(Permission::ProjectsView);
    }

    public function create(User $user, Company $company): bool
    {
        return $user->belongsToCompany($company)
            && $user->in($company)->can(Permission::ProjectsCreate);
    }

    public function update(User $user, Project $project): bool
    {
        return $project->status !== 'archived'
            && $user->belongsToCompany($project->company)
            && $user->in($project->company)->can(Permission::ProjectsUpdate);
    }

    public function delete(User $user, Project $project): bool
    {
        return $user->belongsToCompany($project->company)
            && $user->in($project->company)->can(Permission::ProjectsDelete);
    }
}
```

Task policies follow the same pattern by resolving the company through `$task->project->company`.

```php
namespace App\Policies;

use App\Enums\Permission;
use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    public function view(User $user, Task $task): bool
    {
        $company = $task->project->company;

        return $user->belongsToCompany($company)
            && $user->in($company)->can(Permission::TasksView);
    }

    public function update(User $user, Task $task): bool
    {
        $company = $task->project->company;

        return $user->belongsToCompany($company)
            && $user->in($company)->can(Permission::TasksUpdate);
    }
}
```

## 7. Set Up Routes

Use the generated company middleware to prove membership and set the current company. Use the `access` middleware for simple permission checks.

```php
use App\Http\Controllers\BillingController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\TaskController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::prefix('{current_company}')
        ->middleware('company')
        ->group(function () {
            Route::get('dashboard', DashboardController::class)->name('dashboard');

            Route::get('projects', [ProjectController::class, 'index'])
                ->name('projects.index')
                ->middleware('access:projects.view,current_company');

            Route::post('projects', [ProjectController::class, 'store'])
                ->name('projects.store')
                ->middleware('access:projects.create,current_company');

            Route::get('billing', [BillingController::class, 'show'])
                ->name('billing.show')
                ->middleware('access:billing.view,current_company');
        });

    Route::patch('tasks/{task}', [TaskController::class, 'update']);
    Route::delete('tasks/{task}', [TaskController::class, 'destroy']);
});
```

If you generated notification helpers, the command also adds a protected invitation creation route in `routes/company-invitations.php`:

```php
Route::post('{company:slug}/invitations', [CompanyInvitationController::class, 'store'])
    ->middleware(['auth', 'company:Admin'])
    ->name('company.invitations.store');
```

Customize `CompanyInvitationNotification`, the `store` validation rules, and middleware before exposing this endpoint to users.

The generated middleware resolves `{current_company}`, checks membership, and updates `users.current_company_id`. The generated `AppServiceProvider` URL defaults let `route('dashboard')` include the current company slug automatically.

Nested routes like `tasks/{task}` can rely on policies because the company is resolved through relationships.

## 8. Build Controllers

Keep controllers thin and authorize through policies.

```php
namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProjectController extends Controller
{
    public function index(Company $current_company)
    {
        Gate::authorize('viewAny', [Project::class, $current_company]);

        return inertia('Projects/Index', [
            'company' => $current_company,
            'projects' => $current_company->projects()->paginate(),
        ]);
    }

    public function store(Request $request, Company $current_company)
    {
        Gate::authorize('create', [Project::class, $current_company]);

        $project = $current_company->projects()->create(
            $request->validate(['name' => ['required', 'string', 'max:255']])
        );

        return redirect()->route('projects.show', $project);
    }
}
```

## 9. Invite Company Members

Invitations are app membership records first. When `--notifications` is enabled, the generated `CompanyInvitationController@store` creates a `company_invitations` row and sends `CompanyInvitationNotification` to the invited email.

The generated route is:

```php
Route::post('{company:slug}/invitations', [CompanyInvitationController::class, 'store'])
    ->middleware(['auth', 'company:Admin'])
    ->name('company.invitations.store');
```

A simple invite form can post to that route:

```blade
<form method="POST" action="{{ route('company.invitations.store', $company) }}">
    @csrf

    <input type="email" name="email" required>

    <select name="role" required>
        <option value="{{ \App\Enums\CompanyRole::Admin->value }}">Admin</option>
        <option value="{{ \App\Enums\CompanyRole::Member->value }}">Member</option>
    </select>

    <button type="submit">Send invitation</button>
</form>
```

The notification links to `company.invitations.show`. Existing users can accept while authenticated; brand-new users are redirected to the generated invited-registration page. After registration, the user is created, attached to the company with the invited role, logged in, and redirected to the configured `access.invitations.redirect_after_accept` route.

Membership and access permissions are still separate. If you want invited users to receive Laravel Access roles immediately, add the matching access assignment inside the generated `acceptInvitation` method:

```php
$user->in($invitation->company)->assignRole($invitation->role);
```

That keeps the generated `company_members.role` value and Laravel Access role assignment aligned.

## 10. Share Permissions With Inertia

Use the current company relation generated by `access:scope`.

```php
use App\Enums\Permission;
use Maxiviper117\Access\Facades\Access;

'access' => fn () => $request->user()?->company
    ? Access::for($request->user())
        ->in($request->user()->company)
        ->toArray([
            Permission::ProjectsView,
            Permission::ProjectsCreate,
            Permission::ProjectsUpdate,
            Permission::ProjectsDelete,
            Permission::TasksView,
            Permission::TasksCreate,
            Permission::TasksUpdate,
            Permission::TasksDelete,
            Permission::CompanyMembersInvite,
            Permission::BillingView,
        ])
    : [],
```

The frontend receives a simple permission map:

```php
[
    'projects.view' => true,
    'projects.create' => true,
    'projects.update' => false,
    'company.members.invite' => true,
]
```

## 11. Seed the Database

Create users, a company, membership rows, and scoped access assignments. Membership and authorization are separate writes.

```php
namespace Database\Seeders;

use App\Enums\CompanyRole;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;

class AccessSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::firstOrCreate(
            ['email' => 'owner@example.com'],
            ['name' => 'Owner', 'password' => bcrypt('password')]
        );

        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'Admin', 'password' => bcrypt('password')]
        );

        $member = User::firstOrCreate(
            ['email' => 'member@example.com'],
            ['name' => 'Member', 'password' => bcrypt('password')]
        );

        $company = Company::query()->firstOrCreate(
            ['slug' => 'acme-corp'],
            ['name' => 'Acme Corp']
        );

        $company->users()->syncWithoutDetaching([
            $owner->id => ['role' => CompanyRole::Owner->value],
            $admin->id => ['role' => CompanyRole::Admin->value],
            $member->id => ['role' => CompanyRole::Member->value],
        ]);

        $owner->in($company)->assignRole(CompanyRole::Owner);
        $admin->in($company)->assignRole(CompanyRole::Admin);
        $member->in($company)->assignRole(CompanyRole::Member);

        $owner->switchCompany($company);
    }
}
```

Run it:

```bash
php artisan db:seed --class=AccessSeeder
```

For production seed/update patterns, including global-only apps, read [Seed roles and permissions](/how-to/seed-roles-and-permissions).

## 12. Write Tests

Test permission checks at the policy level:

```php
namespace Tests\Unit;

use App\Enums\CompanyRole;
use App\Enums\Permission;
use App\Models\Company;
use App\Models\User;
use Tests\TestCase;

class ProjectPolicyTest extends TestCase
{
    public function test_owner_can_create_projects(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();

        $company->users()->attach($user, ['role' => CompanyRole::Owner->value]);
        $user->in($company)->assignRole(CompanyRole::Owner);

        $this->assertTrue(
            $user->in($company)->can(Permission::ProjectsCreate)
        );
    }

    public function test_member_cannot_create_projects(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();

        $company->users()->attach($user, ['role' => CompanyRole::Member->value]);
        $user->in($company)->assignRole(CompanyRole::Member);

        $this->assertFalse(
            $user->in($company)->can(Permission::ProjectsCreate)
        );
    }

    public function test_permission_is_scoped_to_company(): void
    {
        $user = User::factory()->create();
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        $companyA->users()->attach($user, ['role' => CompanyRole::Owner->value]);
        $companyB->users()->attach($user, ['role' => CompanyRole::Member->value]);

        $user->in($companyA)->assignRole(CompanyRole::Owner);
        $user->in($companyB)->assignRole(CompanyRole::Member);

        $this->assertTrue($user->in($companyA)->can(Permission::ProjectsDelete));
        $this->assertFalse($user->in($companyB)->can(Permission::ProjectsDelete));
    }
}
```

Test HTTP routes with the generated company middleware and access middleware:

```php
namespace Tests\Feature;

use App\Enums\CompanyRole;
use App\Models\Company;
use App\Models\User;
use Tests\TestCase;

class ProjectCreationTest extends TestCase
{
    public function test_owner_can_create_project_via_http(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();

        $company->users()->attach($user, ['role' => CompanyRole::Owner->value]);
        $user->in($company)->assignRole(CompanyRole::Owner);

        $response = $this
            ->actingAs($user)
            ->post("/{$company->slug}/projects", ['name' => 'New Project']);

        $response->assertRedirect();
    }

    public function test_member_cannot_create_project_via_http(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();

        $company->users()->attach($user, ['role' => CompanyRole::Member->value]);
        $user->in($company)->assignRole(CompanyRole::Member);

        $response = $this
            ->actingAs($user)
            ->post("/{$company->slug}/projects", ['name' => 'New Project']);

        $response->assertForbidden();
    }
}
```

## 13. Debug

Inspect a user's permissions in a company:

```bash
php artisan access:debug owner@example.com --scope=company:acme-corp
```

Clear the cache after config changes:

```bash
php artisan access:clear
```

Preview role changes before syncing:

```bash
php artisan access:sync --dry-run
```

## What's Next

This example uses generated company scope scaffolding. For the lower-level architecture, read [Scaffold team scopes](/how-to/scaffold-team-scopes) and the [mental model](/explanation/mental-model).
