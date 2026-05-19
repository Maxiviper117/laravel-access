<?php

use Illuminate\Support\Facades\File;

it('publishes config and migrations when installing', function () {
    $configPath = config_path('access.php');
    $enumPath = app_path('Enums/Permission.php');

    File::delete($configPath);
    File::delete($enumPath);

    foreach (File::glob(database_path('migrations/*_create_access_table.php')) as $migrationPath) {
        File::delete($migrationPath);
    }

    try {
        $this->artisan('access:install --enum')->assertSuccessful();

        expect($configPath)->toBeFile()
            ->and(File::glob(database_path('migrations/*_create_access_table.php')))->toHaveCount(1)
            ->and($enumPath)->toBeFile()
            ->and(File::get($configPath))->toContain("'permission_enum' => \\App\\Enums\\Permission::class");
    } finally {
        File::delete($configPath);
        File::delete($enumPath);

        foreach (File::glob(database_path('migrations/*_create_access_table.php')) as $migrationPath) {
            File::delete($migrationPath);
        }
    }
});
