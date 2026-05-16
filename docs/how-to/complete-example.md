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

Sync:

```bash
php artisan access:sync
```

## 4. Create Models

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    protected $fillable = ['name', 'slug'];
}
```

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    protected $fillable = ['company_id', 'name', 'status'];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
```

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    protected $fillable = ['project_id', 'title', 'assigned_to', 'status'];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}
```

Company membership is tracked by your own `company_user` pivot table — Laravel Access does not manage memberships.

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

Register policies:

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
Route::middleware('auth')->group(function () {

    // Scoped routes — requires a company parameter
    Route::prefix('companies/{company}')->group(function () {

        Route::get('projects', [ProjectController::class, 'index'])
            ->middleware('access:projects.view,company');

        Route::post('projects', [ProjectController::class, 'store'])
            ->middleware('access:projects.create,company');

        Route::get('billing', [BillingController::class, 'show'])
            ->middleware('access:billing.view,company');
    });

    // Nested routes — policy handles the permission check
    Route::patch('tasks/{task}', [TaskController::class, 'update']);
    Route::delete('tasks/{task}', [TaskController::class, 'destroy']);
});
```

The middleware resolves `company` from the route parameter, looks up the model, and checks the permission in that scope. If the user lacks the permission, it returns `403`.

Nested resources like `tasks/{task}` don't need middleware — the policy resolves the company through the relationship.

## 7. Build Controllers

Keep controllers thin. Authorize through policies:

```php
namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Project;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function index(Company $company)
    {
        $this->authorize('viewAny', [Project::class, $company]);

        $projects = $company->projects()->paginate();

        return inertia('Projects/Index', [
            'company' => $company,
            'projects' => $projects,
        ]);
    }

    public function store(Request $request, Company $company)
    {
        $this->authorize('create', [Project::class, $company]);

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

Create a seeder that sets up roles-per-company for testing:

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
        $owner = User::where('email', 'owner@example.com')->first();
        $admin = User::where('email', 'admin@example.com')->first();
        $manager = User::where('email', 'manager@example.com')->first();
        $member = User::where('email', 'member@example.com')->first();

        $company = Company::factory()->create(['name' => 'Acme Corp']);

        // Each user gets a different role in the same company
        $owner->in($company)->assignRole('Owner');
        $admin->in($company)->assignRole('Admin');
        $manager->in($company)->assignRole('Manager');
        $member->in($company)->assignRole('Member');
    }
}
```

Seed and sync:

```bash
php artisan db:seed --class=AccessSeeder
php artisan access:sync
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
            ->postJson("/companies/{$company->id}/projects", [
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
            ->postJson("/companies/{$company->id}/projects", [
                'name' => 'New Project',
            ]);

        $response->assertForbidden();
    }
}
```

## 11. Debug

Inspect a user's permissions in a company:

```bash
php artisan access:debug owner@example.com --scope=company:1
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
