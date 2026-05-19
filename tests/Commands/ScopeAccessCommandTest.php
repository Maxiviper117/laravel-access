<?php

use Illuminate\Support\Facades\File;

function cleanScopeScaffold(): void
{
    foreach ([
        app_path('Models/Company.php'),
        app_path('Models/Membership.php'),
        app_path('Models/CompanyInvitation.php'),
        app_path('Concerns/HasCompanies.php'),
        app_path('Http/Middleware/EnsureCompanyMembership.php'),
        app_path('Enums/CompanyRole.php'),
        app_path('Enums/CompanyPermission.php'),
        app_path('Http/Controllers/Auth/CompanyInvitationController.php'),
        resource_path('views/auth/company-invitation-error.blade.php'),
        resource_path('views/auth/company-invited-register.blade.php'),
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
    $bootstrapPath = base_path('bootstrap/app.php');
    $originalBootstrap = File::exists($bootstrapPath) ? File::get($bootstrapPath) : null;

    File::delete($configPath);
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
            ->and(base_path('routes/company-invitations.php'))->toBeFile()
            ->and(File::glob(database_path('migrations/*_create_companies_table.php')))->toHaveCount(1)
            ->and(File::glob(database_path('migrations/*_create_company_members_table.php')))->toHaveCount(1)
            ->and(File::glob(database_path('migrations/*_create_company_invitations_table.php')))->toHaveCount(1)
            ->and(File::glob(database_path('migrations/*_add_current_company_id_to_users_table.php')))->toHaveCount(1);

        expect(File::get(app_path('Models/Company.php')))
            ->toContain("protected \$fillable = ['name', 'slug'];")
            ->toContain("return 'slug';")
            ->toContain("'company_members'");

        expect(File::get(config_path('access.php')))
            ->toContain("'default_scope_model' => \\App\\Models\\Company::class")
            ->toContain("'model' => \\App\\Models\\Company::class")
            ->toContain("'singular' => 'company'")
            ->toContain("'plural' => 'companies'")
            ->toContain("'require_existing_user' => false");

        expect(File::get($bootstrapPath))
            ->toContain("'company' => \\App\\Http\\Middleware\\EnsureCompanyMembership::class")
            ->toContain("require __DIR__.'/../routes/company-invitations.php';");
    } finally {
        File::delete($configPath);
        if ($originalBootstrap === null) {
            File::delete($bootstrapPath);
        } else {
            File::put($bootstrapPath, $originalBootstrap);
        }
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
            app_path('Enums/AlumnusPermission.php'),
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
