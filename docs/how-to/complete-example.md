---
title: Complete Example
---

# Complete Example: Project Management SaaS

This example walks through a real Laravel app using Laravel Access for scoped authorization — from install to tests.

## Scenario

A project management SaaS where:

- Users belong to companies (membership tracked by your app)
- Companies have projects and tasks
- A user's role is per-company — Owner in one, Member in another
- Roles control CRUD access at the company level

## Models

| Model | Purpose | Scope |
|---|---|---|
| `User` | Authenticatable, uses `HasAccess` | Actor |
| `Company` | Tenant / organization | Scope model |
| `Project` | Belongs to company | Object |
| `Task` | Belongs to project | Object |

## 1. Install

```bash
composer require maxiviper117/laravel-access
php artisan access:install --enum
php artisan migrate
```

Add the trait to `User`:

```php
use Maxiviper117\Access\Concerns\HasAccess;

class User extends Authenticatable
{
    use HasAccess;
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
    case MembersInvite = 'members.invite';
    case MembersManage = 'members.manage';
    case BillingView = 'billing.view';
    case CompanyUpdate = 'company.update';
    case SystemManage = 'system.manage';
}
```

## 3. Configure Roles

Edit `config/access.php`. Lines with a red `-` are removed from the stub. Lines with a green `+` are what you write:

```php
use App\Enums\Permission;
use App\Models\Company;

return [
    'user_model' => 'App\\Models\\User',

    'permission_enum' => null, // [!code --]
    'permission_enum' => Permission::class, // [!code ++]

    'default_scope_model' => null, // [!code --]
    'default_scope_model' => Company::class, // [!code ++]

    'cache' => [
        'enabled' => env('APP_ENV') !== 'testing',
        'key' => 'access.permissions',
        'ttl' => null,
    ],

    'global_roles' => [ // [!code --]
        // 'Platform Admin' => [ // [!code --]
        //     App\Enums\Permission::SystemManage, // [!code --]
        // ], // [!code --]
    ], // [!code --]
    'global_roles' => [ // [!code ++]
        'Platform Admin' => [ // [!code ++]
            Permission::SystemManage, // [!code ++]
        ], // [!code ++]
    ], // [!code ++]

    'roles' => [ // [!code --]
        // 'Owner' => [ // [!code --]
        //     App\Enums\Permission::UsersView, // [!code --]
        // ], // [!code --]
    ], // [!code --]
    'roles' => [ // [!code ++]
        'Owner' => [ // [!code ++]
            Permission::ProjectsView, // [!code ++]
            Permission::ProjectsCreate, // [!code ++]
            Permission::ProjectsUpdate, // [!code ++]
            Permission::ProjectsDelete, // [!code ++]
            Permission::TasksView, // [!code ++]
            Permission::TasksCreate, // [!code ++]
            Permission::TasksUpdate, // [!code ++]
            Permission::TasksDelete, // [!code ++]
            Permission::MembersInvite, // [!code ++]
            Permission::MembersManage, // [!code ++]
            Permission::BillingView, // [!code ++]
            Permission::CompanyUpdate, // [!code ++]
        ], // [!code ++]
        'Admin' => [ // [!code ++]
            Permission::ProjectsView, // [!code ++]
            Permission::ProjectsCreate, // [!code ++]
            Permission::ProjectsUpdate, // [!code ++]
            Permission::ProjectsDelete, // [!code ++]
            Permission::TasksView, // [!code ++]
            Permission::TasksCreate, // [!code ++]
            Permission::TasksUpdate, // [!code ++]
            Permission::TasksDelete, // [!code ++]
            Permission::MembersInvite, // [!code ++]
            Permission::BillingView, // [!code ++]
        ], // [!code ++]
        'Manager' => [ // [!code ++]
            Permission::ProjectsView, // [!code ++]
            Permission::ProjectsCreate, // [!code ++]
            Permission::ProjectsUpdate, // [!code ++]
            Permission::TasksView, // [!code ++]
            Permission::TasksCreate, // [!code ++]
            Permission::TasksUpdate, // [!code ++]
        ], // [!code ++]
        'Member' => [ // [!code ++]
            Permission::ProjectsView, // [!code ++]
            Permission::TasksView, // [!code ++]
            Permission::TasksCreate, // [!code ++]
            Permission::TasksUpdate, // [!code ++]
        ], // [!code ++]
    ], // [!code ++]

    'gate_before' => [
        'enabled' => false,
        'global_role' => 'Platform Admin',
    ],
];
```

## 4. Create Models & Migrations

Create the following migrations and models. The `users` table already exists from Laravel's default install — you only need to add the `company_user` pivot and the `projects`/`tasks` tables.

### Migrations

```php
// database/migrations/xxxx_xx_xx_000001_create_companies_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
```

```php
// database/migrations/xxxx_xx_xx_000002_create_company_user_pivot_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_user', function (Blueprint $table) {
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['company_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_user');
    }
};
```

```php
// database/migrations/xxxx_xx_xx_000003_create_projects_table.php
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
// database/migrations/xxxx_xx_xx_000004_create_tasks_table.php
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

### Models

Add the `companies()` relationship to `User` (alongside the `HasAccess` trait from section 1):

```php
// app/Models/User.php

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

// Inside the class:
public function companies(): BelongsToMany
{
    return $this->belongsToMany(Company::class);
}
```

```php
// app/Models/Company.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    /** @use HasFactory<\Database\Factories\CompanyFactory> */
    use HasFactory;

    protected $fillable = ['name', 'slug'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }
}
```

```php
// app/Models/Project.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    /** @use HasFactory<\Database\Factories\ProjectFactory> */
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

```php
// app/Models/Task.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    /** @use HasFactory<\Database\Factories\TaskFactory> */
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

Company membership is tracked by your own `company_user` pivot table — Laravel Access does not manage memberships. No separate model is needed; `belongsToMany` on both `User` and `Company` handles it transparently.

### Factories

The seeder uses `Company::factory()`, so create factories that produce sensible defaults:

```php
// database/factories/CompanyFactory.php
namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Company>
 */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'slug' => Str::slug(fake()->unique()->company()),
        ];
    }
}
```

```php
// database/factories/ProjectFactory.php
namespace Database\Factories;

use App\Models\Company;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => fake()->sentence(3),
            'status' => 'active',
        ];
    }
}
```

```php
// database/factories/TaskFactory.php
namespace Database\Factories;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Task>
 */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'title' => fake()->sentence(6),
            'assigned_to' => User::factory(),
            'status' => 'todo',
        ];
    }
}
```

Now sync the role and permission definitions into the database (migrations and models must exist first since `Company::class` is referenced in the config):

```bash
php artisan migrate
php artisan access:sync
```

## 5. Write Policies

Policy methods check the permission and any object-specific rules together:

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
        return $user->in($company)->can(Permission::ProjectsView);
    }

    public function view(User $user, Project $project): bool
    {
        return $user->in($project->company)->can(Permission::ProjectsView);
    }

    public function create(User $user, Company $company): bool
    {
        return $user->in($company)->can(Permission::ProjectsCreate);
    }

    public function update(User $user, Project $project): bool
    {
        return $user->in($project->company)->can(Permission::ProjectsUpdate);
    }

    public function delete(User $user, Project $project): bool
    {
        return $user->in($project->company)->can(Permission::ProjectsDelete);
    }
}
```

Combine permission checks with object state:

```php
public function update(User $user, Project $project): bool
{
    return $project->status !== 'archived'
        && $user->in($project->company)->can(Permission::ProjectsUpdate);
}
```

TaskPolicy follows the same pattern, resolving the company through `$task->project->company`:

```php
namespace App\Policies;

use App\Enums\Permission;
use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    public function update(User $user, Task $task): bool
    {
        return $user->in($task->project->company)->can(Permission::TasksUpdate);
    }

    public function view(User $user, Task $task): bool
    {
        return $user->in($task->project->company)->can(Permission::TasksView);
    }
}
```

Register policies (optional — Laravel auto-discovers policies when the model and policy follow naming conventions, e.g. `Project` → `ProjectPolicy`):

```php
// App\Providers\AuthServiceProvider

use App\Models\Project;
use App\Models\Task;
use App\Policies\ProjectPolicy;
use App\Policies\TaskPolicy;

protected $policies = [
    Project::class => ProjectPolicy::class,
    Task::class => TaskPolicy::class,
];
```

## 6. Set Up Routes and Middleware

Use the `access` middleware for route-level checks:

```php
// routes/web.php
<?php

use App\Http\Controllers\BillingController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\TaskController;
use App\Http\Middleware\SetActiveCompany;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

Route::middleware('auth')->group(function () {

    // Scoped routes — requires a company parameter
    Route::prefix('companies/{company}')
        ->middleware(SetActiveCompany::class)
        ->group(function () {

            Route::get('projects', [ProjectController::class, 'index'])
                ->name('companies.projects.index')
                ->middleware('access:projects.view,company');

            Route::post('projects', [ProjectController::class, 'store'])
                ->name('companies.projects.store')
                ->middleware('access:projects.create,company');

            Route::get('billing', [BillingController::class, 'show'])
                ->name('companies.billing.show')
                ->middleware('access:billing.view,company');
        });

    // Nested routes — policy handles the permission check
    Route::patch('tasks/{task}', [TaskController::class, 'update']);
    Route::delete('tasks/{task}', [TaskController::class, 'destroy']);
});

require __DIR__.'/settings.php';
```

The `Company` model uses `getRouteKeyName()` to resolve route parameters by `slug` instead of `id`. The middleware resolves `company` from the route parameter, looks up the model by slug, and checks the permission in that scope. If the user lacks the permission, it returns `403`.

Nested resources like `tasks/{task}` don't need middleware — the policy resolves the company through the relationship.

### Active Company Middleware

The Inertia sharing and other places need to know which company the user is currently acting within. Create middleware that captures it from the route:

```php
// app/Http/Middleware/SetActiveCompany.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetActiveCompany
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($company = $request->route('company')) {
            session(['active_company_id' => $company instanceof \App\Models\Company ? $company->id : $company]);
        }

        return $next($request);
    }
}
```

## 7. Build Controllers

Keep controllers thin. Authorize through policies.

> **Laravel 11+ note:** The base controller no longer includes `AuthorizesRequests` by default, so `$this->authorize()` requires adding `use Illuminate\Foundation\Auth\Access\AuthorizesRequests` to your controller. Prefer `Gate::authorize()` — it works without the trait and anywhere in your codebase.

```php
namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProjectController extends Controller
{
    public function index(Company $company)
    {
        Gate::authorize('viewAny', [Project::class, $company]);

        $projects = $company->projects()->paginate();

        return inertia('Projects/Index', [
            'company' => $company,
            'projects' => $projects,
        ]);
    }

    public function store(Request $request, Company $company)
    {
        Gate::authorize('create', [Project::class, $company]);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $project = $company->projects()->create($validated);

        return redirect()->route('projects.show', [$company, $project]);
    }
}
```

The policy receives `Project::class` and the `$company` parameter, matching the `viewAny` and `create` signatures.

## 8. Share Permissions With Inertia

Share only the permissions the current page needs, resolved against the active company:

```php
use App\Enums\Permission;
use Maxiviper117\Access\Facades\Access;

// In HandleInertiaRequests::share()
'access' => fn () => $request->user() && session('active_company_id')
    ? Access::for($request->user())
        ->in(Company::find(session('active_company_id')))
        ->toArray([
            Permission::ProjectsView,
            Permission::ProjectsCreate,
            Permission::ProjectsUpdate,
            Permission::ProjectsDelete,
            Permission::TasksView,
            Permission::TasksCreate,
            Permission::TasksUpdate,
            Permission::TasksDelete,
            Permission::MembersInvite,
            Permission::BillingView,
        ])
    : [],
```

The frontend receives:

```php
[
    'projects.view' => true,
    'projects.create' => true,
    'projects.update' => false,
    'projects.delete' => false,
    'tasks.view' => true,
    'tasks.create' => true,
    'tasks.update' => true,
    'tasks.delete' => false,
    'members.invite' => true,
    'billing.view' => true,
]
```

Use it to shape the UI:

```tsx
// React example
const access = page.props.access;

return (
    <div>
        {access['projects.create'] && <button>New Project</button>}

        {access['tasks.create'] && <AddTaskForm />}
    </div>
);
```

## 9. Seed the Database

Create a seeder that sets up users, a company, and roles-per-company for testing:

```php
namespace Database\Seeders;

use App\Enums\Permission;
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
        $manager = User::firstOrCreate(
            ['email' => 'manager@example.com'],
            ['name' => 'Manager', 'password' => bcrypt('password')]
        );
        $member = User::firstOrCreate(
            ['email' => 'member@example.com'],
            ['name' => 'Member', 'password' => bcrypt('password')]
        );

        $company = Company::factory()->create(['name' => 'Acme Corp', 'slug' => 'acme-corp']);

        // Attach users to the company (membership)
        $company->users()->attach([
            $owner->id,
            $admin->id,
            $manager->id,
            $member->id,
        ]);

        // Each user gets a different role in the same company
        $owner->in($company)->assignRole('Owner');
        $admin->in($company)->assignRole('Admin');
        $manager->in($company)->assignRole('Manager');
        $member->in($company)->assignRole('Member');
    }
}
```

Run the seeder (`access:sync` was already run in step 4 — the seeder only assigns existing roles to users, no re-sync needed):

```bash
php artisan db:seed --class=AccessSeeder
```

## 10. Write Tests

Test permission checks at the policy level:

```php
namespace Tests\Unit;

use App\Enums\Permission;
use App\Models\Company;
use App\Models\User;
use Tests\TestCase;

class ProjectPolicyTest extends TestCase
{
    public function test_owner_can_create_projects()
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();

        $user->in($company)->assignRole('Owner');

        $this->assertTrue(
            $user->in($company)->can(Permission::ProjectsCreate)
        );
    }

    public function test_member_cannot_create_projects()
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();

        $user->in($company)->assignRole('Member');

        $this->assertFalse(
            $user->in($company)->can(Permission::ProjectsCreate)
        );
    }

    public function test_permission_is_scoped_to_company()
    {
        $user = User::factory()->create();
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        // Owner in company A, Member in company B
        $user->in($companyA)->assignRole('Owner');
        $user->in($companyB)->assignRole('Member');

        $this->assertTrue(
            $user->in($companyA)->can(Permission::ProjectsDelete)
        );

        $this->assertFalse(
            $user->in($companyB)->can(Permission::ProjectsDelete)
        );
    }

    public function test_sync_creates_permissions()
    {
        $this->artisan('access:sync')
            ->assertExitCode(0);

        $this->assertDatabaseHas('access_permissions', [
            'name' => Permission::ProjectsCreate->value,
        ]);
    }
}
```

Test HTTP routes with the middleware:

```php
namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Tests\TestCase;

class ProjectCreationTest extends TestCase
{
    public function test_owner_can_create_project_via_api()
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();

        $user->in($company)->assignRole('Owner');

        $response = $this
            ->actingAs($user)
            ->postJson("/companies/{$company->slug}/projects", [
                'name' => 'New Project',
            ]);

        $response->assertCreated();
    }

    public function test_member_cannot_create_project_via_api()
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();

        $user->in($company)->assignRole('Member');

        $response = $this
            ->actingAs($user)
            ->postJson("/companies/{$company->slug}/projects", [
                'name' => 'New Project',
            ]);

        $response->assertForbidden();
    }
}
```

## 11. Debug

Inspect a user's permissions in a company:

```bash
php artisan access:debug owner@example.com --scope=company:acme-corp
```

Clear the cache after making config changes during development:

```bash
php artisan access:clear
```

Dry-run before syncing role changes in production:

```bash
php artisan access:sync --dry-run
```

## What's Next

This example covers scoped permissions. For apps without multi-tenancy, use [global-only mode](/tutorials/getting-started#path-b-global-only-setup). For deeper understanding of the design, read the [mental model](/explanation/mental-model).
