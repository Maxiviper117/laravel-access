<?php

use Illuminate\Support\Facades\File;

function cleanScopeScaffold(): void
{
    File::deleteDirectory(resource_path('js/Pages/Auth'));
    File::deleteDirectory(resource_path('js/Pages/auth'));

    foreach ([
        app_path('Models/Company.php'),
        app_path('Models/Membership.php'),
        app_path('Models/CompanyInvitation.php'),
        app_path('Concerns/HasCompanies.php'),
        app_path('Http/Middleware/EnsureCompanyMembership.php'),
        app_path('Enums/CompanyRole.php'),
        app_path('Http/Controllers/Auth/CompanyInvitationController.php'),
        app_path('Notifications/CompanyInvitationNotification.php'),
        resource_path('views/auth/company-invitation-error.blade.php'),
        resource_path('views/auth/company-invited-register.blade.php'),
        resource_path('js/Pages/auth/CompanyInvitationError.tsx'),
        resource_path('js/Pages/auth/CompanyInvitedRegister.tsx'),
        resource_path('js/Pages/auth/CompanyInvitationError.vue'),
        resource_path('js/Pages/auth/CompanyInvitedRegister.vue'),
        resource_path('js/Pages/auth/CompanyInvitationError.svelte'),
        resource_path('js/Pages/auth/CompanyInvitedRegister.svelte'),
        base_path('routes/company-invitations.php'),
    ] as $path) {
        File::delete($path);
    }

    foreach (File::glob(database_path('migrations/*_companies_table.php')) as $path) {
        File::delete($path);
    }

    foreach (File::glob(database_path('migrations/*_company_members_table.php')) as $path) {
        File::delete($path);
    }

    foreach (File::glob(database_path('migrations/*_company_invitations_table.php')) as $path) {
        File::delete($path);
    }

    foreach (File::glob(database_path('migrations/*_current_company_id_to_users_table.php')) as $path) {
        File::delete($path);
    }
}

it('scaffolds a renamed scope from the name option', function () {
    $configPath = config_path('access.php');
    $enumPath = app_path('Enums/Permission.php');
    $bootstrapPath = base_path('bootstrap/app.php');
    $originalBootstrap = File::exists($bootstrapPath) ? File::get($bootstrapPath) : null;
    $originalEnum = File::exists($enumPath) ? File::get($enumPath) : null;

    File::delete($configPath);
    File::ensureDirectoryExists(dirname($enumPath));
    File::put($enumPath, <<<'PHP'
<?php

namespace App\Enums;

enum Permission: string
{
    case UsersView = 'users.view';
}
PHP);
    File::ensureDirectoryExists(dirname($bootstrapPath));
    File::put($bootstrapPath, <<<'PHP'
<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
PHP);
    cleanScopeScaffold();

    try {
        $this->artisan('access:scope --name=company --no-concern')
            ->assertSuccessful();

        expect(app_path('Models/Company.php'))->toBeFile()
            ->and(app_path('Models/CompanyInvitation.php'))->toBeFile()
            ->and(app_path('Concerns/HasCompanies.php'))->toBeFile()
            ->and(app_path('Http/Middleware/EnsureCompanyMembership.php'))->toBeFile()
            ->and(app_path('Enums/CompanyRole.php'))->toBeFile()
            ->and(app_path('Enums/CompanyPermission.php'))->not->toBeFile()
            ->and(base_path('routes/company-invitations.php'))->toBeFile()
            ->and(resource_path('views/auth/company-invitation-error.blade.php'))->toBeFile()
            ->and(resource_path('views/auth/company-invited-register.blade.php'))->toBeFile()
            ->and(File::glob(database_path('migrations/*_create_companies_table.php')))->toHaveCount(1)
            ->and(File::glob(database_path('migrations/*_create_company_members_table.php')))->toHaveCount(1)
            ->and(File::glob(database_path('migrations/*_create_company_invitations_table.php')))->toHaveCount(1)
            ->and(File::glob(database_path('migrations/*_add_current_company_id_to_users_table.php')))->toHaveCount(1);

        expect(File::get(app_path('Models/Company.php')))
            ->toContain("protected \$fillable = ['name', 'slug'];")
            ->toContain("return 'slug';")
            ->toContain("'company_members'");

        expect(File::get(app_path('Models/Membership.php')))
            ->toContain('use App\Enums\CompanyRole;')
            ->toContain("'role' => CompanyRole::class");

        expect(File::get(app_path('Concerns/HasCompanies.php')))
            ->toContain('use App\Enums\CompanyRole;')
            ->toContain('->using(Membership::class)')
            ->toContain('public function companyRole(Company $scope): ?CompanyRole');

        expect(File::get(app_path('Http/Middleware/EnsureCompanyMembership.php')))
            ->toContain('$role = $user->companyRole($scope);')
            ->toContain('abort_if(! $role || $role->level() < CompanyRole::from($minimumRole)->level(), 403);');

        expect(File::get(app_path('Http/Controllers/Auth/CompanyInvitationController.php')))
            ->not->toContain('use Inertia\\Inertia;')
            ->toContain("return view('auth.company-invited-register'")
            ->toContain("'companyName' => \$invitation->company->name");

        expect(File::get(resource_path('views/auth/company-invitation-error.blade.php')))
            ->toContain('class="mx-auto flex min-h-screen')
            ->toContain('rounded-lg border border-gray-200 bg-white');

        expect(File::get(resource_path('views/auth/company-invited-register.blade.php')))
            ->toContain('class="space-y-5"')
            ->toContain('class="w-full rounded-md bg-gray-950');

        expect(File::get(config_path('access.php')))
            ->toContain("'default_scope_model' => \\App\\Models\\Company::class")
            ->toContain("'model' => \\App\\Models\\Company::class")
            ->toContain("'singular' => 'company'")
            ->toContain("'plural' => 'companies'")
            ->toContain("'require_existing_user' => false");

        expect(File::get($bootstrapPath))
            ->toContain("'company' => \\App\\Http\\Middleware\\EnsureCompanyMembership::class")
            ->toContain("\\Illuminate\\Support\\Facades\\Route::middleware('web')")
            ->toContain("->group(base_path('routes/company-invitations.php'));");

        expect(File::get($enumPath))
            ->toContain("case CompanyMembersView = 'company.members.view';")
            ->toContain("case CompanyMembersInvite = 'company.members.invite';")
            ->toContain("case CompanyMembersManage = 'company.members.manage';")
            ->toContain("case CompanySettingsManage = 'company.settings.manage';");
    } finally {
        File::delete($configPath);
        if ($originalEnum === null) {
            File::delete($enumPath);
        } else {
            File::put($enumPath, $originalEnum);
        }
        if ($originalBootstrap === null) {
            File::delete($bootstrapPath);
        } else {
            File::put($bootstrapPath, $originalBootstrap);
        }
        cleanScopeScaffold();
    }
});

it('can scaffold invitation notification helpers', function () {
    cleanScopeScaffold();

    try {
        $this->artisan('access:scope --name=company --notifications --no-concern')
            ->assertSuccessful();

        expect(app_path('Notifications/CompanyInvitationNotification.php'))->toBeFile();

        expect(File::get(app_path('Http/Controllers/Auth/CompanyInvitationController.php')))
            ->toContain('use App\\Notifications\\CompanyInvitationNotification;')
            ->toContain('public function store(Request $request, Company $company): RedirectResponse')
            ->toContain("Notification::route('mail', \$invitation->email)")
            ->toContain('new CompanyInvitationNotification($invitation)');

        expect(File::get(base_path('routes/company-invitations.php')))
            ->toContain("Route::post('{company:slug}/invitations'")
            ->toContain("->middleware(['auth', 'company:Admin'])");

        expect(File::get(app_path('Notifications/CompanyInvitationNotification.php')))
            ->toContain("route('company.invitations.show', \$this->invitation)");
    } finally {
        cleanScopeScaffold();
    }
});

it('can scaffold inertia react invitation pages', function () {
    cleanScopeScaffold();

    try {
        $this->artisan('access:scope --name=company --frontend=react --no-concern')
            ->assertSuccessful();

        expect(resource_path('js/Pages/auth/CompanyInvitationError.tsx'))->toBeFile()
            ->and(resource_path('js/Pages/auth/CompanyInvitedRegister.tsx'))->toBeFile()
            ->and(resource_path('views/auth/company-invitation-error.blade.php'))->not->toBeFile()
            ->and(resource_path('views/auth/company-invited-register.blade.php'))->not->toBeFile();

        expect(collect(File::directories(resource_path('js/Pages')))->map(fn (string $path) => basename($path))->all())
            ->toContain('auth');

        expect(File::get(app_path('Http/Controllers/Auth/CompanyInvitationController.php')))
            ->toContain('use Inertia\\Inertia;')
            ->toContain("Inertia::render('auth/CompanyInvitationError'")
            ->toContain("Inertia::render('auth/CompanyInvitedRegister'");

        expect(File::get(resource_path('js/Pages/auth/CompanyInvitedRegister.tsx')))
            ->toContain("import { Form } from '@inertiajs/react'")
            ->toContain('Create account')
            ->toContain('className="flex min-h-screen')
            ->toContain('className="w-full rounded-md bg-gray-950');
    } finally {
        cleanScopeScaffold();
    }
});

it('keeps inertia generated page path casing aligned with render names', function () {
    cleanScopeScaffold();

    try {
        $this->artisan('access:scope --name=company --frontend=vue --no-concern')
            ->assertSuccessful();

        expect(resource_path('js/Pages/auth/CompanyInvitationError.vue'))->toBeFile()
            ->and(resource_path('js/Pages/auth/CompanyInvitedRegister.vue'))->toBeFile();

        expect(collect(File::directories(resource_path('js/Pages')))->map(fn (string $path) => basename($path))->all())
            ->toContain('auth');

        expect(File::get(app_path('Http/Controllers/Auth/CompanyInvitationController.php')))
            ->toContain("Inertia::render('auth/CompanyInvitationError'")
            ->toContain("Inertia::render('auth/CompanyInvitedRegister'");

        expect(File::get(resource_path('js/Pages/auth/CompanyInvitedRegister.vue')))
            ->toContain('class="flex min-h-screen')
            ->toContain('class="w-full rounded-md bg-gray-950');
    } finally {
        cleanScopeScaffold();
    }
});

it('uses inertia v3 svelte props syntax for svelte invitation pages', function () {
    cleanScopeScaffold();

    try {
        $this->artisan('access:scope --name=company --frontend=svelte --no-concern')
            ->assertSuccessful();

        expect(resource_path('js/Pages/auth/CompanyInvitationError.svelte'))->toBeFile()
            ->and(resource_path('js/Pages/auth/CompanyInvitedRegister.svelte'))->toBeFile();

        expect(File::get(resource_path('js/Pages/auth/CompanyInvitedRegister.svelte')))
            ->toContain('} = $props()')
            ->toContain('class="flex min-h-screen')
            ->toContain('class="w-full rounded-md bg-gray-950')
            ->not->toContain('export let invitation');

        expect(File::get(resource_path('js/Pages/auth/CompanyInvitationError.svelte')))
            ->toContain('} = $props()')
            ->not->toContain('export let message');
    } finally {
        cleanScopeScaffold();
    }
});

it('supports irregular plural overrides', function () {
    cleanScopeScaffold();

    try {
        $this->artisan('access:scope --name=alumnus --plural=alumni --no-concern')
            ->assertSuccessful();

        expect(app_path('Models/Alumnus.php'))->toBeFile()
            ->and(app_path('Concerns/HasAlumni.php'))->toBeFile()
            ->and(File::glob(database_path('migrations/*_create_alumni_table.php')))->toHaveCount(1);

        expect(File::get(app_path('Concerns/HasAlumni.php')))
            ->toContain('trait HasAlumni')
            ->toContain('public function alumni()');
    } finally {
        foreach ([
            app_path('Models/Alumnus.php'),
            app_path('Models/Membership.php'),
            app_path('Models/AlumnusInvitation.php'),
            app_path('Concerns/HasAlumni.php'),
            app_path('Http/Middleware/EnsureAlumnusMembership.php'),
            app_path('Enums/AlumnusRole.php'),
            app_path('Http/Controllers/Auth/AlumnusInvitationController.php'),
            resource_path('views/auth/alumnus-invitation-error.blade.php'),
            resource_path('views/auth/alumnus-invited-register.blade.php'),
            base_path('routes/alumnus-invitations.php'),
        ] as $path) {
            File::delete($path);
        }

        foreach (File::glob(database_path('migrations/*_alumni_table.php')) as $path) {
            File::delete($path);
        }

        foreach (File::glob(database_path('migrations/*_alumnus_members_table.php')) as $path) {
            File::delete($path);
        }

        foreach (File::glob(database_path('migrations/*_alumnus_invitations_table.php')) as $path) {
            File::delete($path);
        }

        foreach (File::glob(database_path('migrations/*_current_alumnus_id_to_users_table.php')) as $path) {
            File::delete($path);
        }
    }
});
