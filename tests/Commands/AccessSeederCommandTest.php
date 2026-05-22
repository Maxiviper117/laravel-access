<?php

use Illuminate\Support\Facades\File;

function cleanAccessSeeders(): void
{
    foreach ([
        database_path('seeders/CompanyAccessSeeder.php'),
        database_path('seeders/AlumnusAccessSeeder.php'),
        database_path('seeders/DemoCompanySeeder.php'),
    ] as $path) {
        File::delete($path);
    }
}

it('generates an editable scoped access seeder', function (): void {
    cleanAccessSeeders();

    try {
        $this->artisan('access:seeder --name=company')
            ->assertSuccessful();

        $path = database_path('seeders/CompanyAccessSeeder.php');

        expect($path)->toBeFile();

        expect(File::get($path))
            ->toContain('class CompanyAccessSeeder extends Seeder')
            ->toContain('use App\Enums\CompanyRole;')
            ->toContain('use App\Models\Company;')
            ->toContain('// Replace these demo records with the users and companies your app should start with.')
            ->toContain("['email' => 'owner@example.com']")
            ->toContain("['slug' => 'company-demo']")
            ->toContain('// This writes your app-owned company membership state.')
            ->toContain('$company->users()->syncWithoutDetaching([')
            ->toContain("// This separately assigns the scoped Laravel Access role and selects the user's current company.")
            ->toContain('$owner->in($company)->assignRole(CompanyRole::Owner);')
            ->toContain('$owner->switchCompany($company);');
    } finally {
        cleanAccessSeeders();
    }
});

it('supports irregular plural overrides when generating seeders', function (): void {
    cleanAccessSeeders();

    try {
        $this->artisan('access:seeder --name=alumnus --plural=alumni')
            ->assertSuccessful();

        $path = database_path('seeders/AlumnusAccessSeeder.php');

        expect($path)->toBeFile();

        expect(File::get($path))
            ->toContain('class AlumnusAccessSeeder extends Seeder')
            ->toContain('use App\Enums\AlumnusRole;')
            ->toContain('use App\Models\Alumnus;')
            ->toContain('$alumnus->users()->syncWithoutDetaching([')
            ->toContain('$owner->switchAlumnus($alumnus);');
    } finally {
        cleanAccessSeeders();
    }
});

it('allows a custom seeder class name', function (): void {
    cleanAccessSeeders();

    try {
        $this->artisan('access:seeder --name=company --class=DemoCompanySeeder')
            ->assertSuccessful();

        $path = database_path('seeders/DemoCompanySeeder.php');

        expect($path)->toBeFile();

        expect(File::get($path))
            ->toContain('class DemoCompanySeeder extends Seeder');
    } finally {
        cleanAccessSeeders();
    }
});

it('does not overwrite an existing seeder without force', function (): void {
    cleanAccessSeeders();

    $path = database_path('seeders/CompanyAccessSeeder.php');
    File::ensureDirectoryExists(dirname($path));
    File::put($path, 'existing seeder');

    try {
        $this->artisan('access:seeder --name=company')
            ->assertFailed();

        expect(File::get($path))->toBe('existing seeder');

        $this->artisan('access:seeder --name=company --force')
            ->assertSuccessful();

        expect(File::get($path))->toContain('class CompanyAccessSeeder extends Seeder');
    } finally {
        cleanAccessSeeders();
    }
});
