<?php

use Illuminate\Support\Facades\File;

it('publishes config and migrations when installing and inserts HasAccess trait into User model', function () {
    $configPath = config_path('access.php');
    $enumPath = app_path('Enums/Permission.php');
    $userPath = app_path('Models/User.php');
    $createRolePath = app_path('Actions/Access/CreateRole.php');
    $deleteRolePath = app_path('Actions/Access/DeleteRole.php');
    $syncRolePath = app_path('Actions/Access/SyncRolePermissions.php');
    $addPermissionPath = app_path('Actions/Access/AddPermissionToRole.php');
    $removePermissionPath = app_path('Actions/Access/RemovePermissionFromRole.php');

    File::delete($configPath);
    File::delete($enumPath);
    File::delete($userPath);
    File::delete($createRolePath);
    File::delete($deleteRolePath);
    File::delete($syncRolePath);
    File::delete($addPermissionPath);
    File::delete($removePermissionPath);
    File::deleteDirectory(app_path('Actions'));

    foreach (File::glob(database_path('migrations/*_create_access_table.php')) as $migrationPath) {
        File::delete($migrationPath);
    }

    // Create a stub User model
    File::ensureDirectoryExists(dirname($userPath));
    File::put($userPath, <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    protected $guarded = [];
}
PHP);

    try {
        $this->artisan('access:install --enum')
            ->expectsOutputToContain('Added HasAccess trait to User model.')
            ->assertSuccessful();

        expect($configPath)->toBeFile()
            ->and(File::glob(database_path('migrations/*_create_access_table.php')))->toHaveCount(1)
            ->and($enumPath)->toBeFile()
            ->and(File::get($configPath))->toContain("'permission_enums' => [\\App\\Enums\\Permission::class]")
            ->and($createRolePath)->toBeFile()
            ->and($deleteRolePath)->toBeFile()
            ->and($syncRolePath)->toBeFile()
            ->and($addPermissionPath)->toBeFile()
            ->and($removePermissionPath)->toBeFile();

        $userContents = File::get($userPath);
        expect($userContents)->toContain('use Maxiviper117\Access\Concerns\HasAccess;')
            ->and($userContents)->toContain('use HasAccess;');
    } finally {
        File::delete($configPath);
        File::delete($enumPath);
        File::delete($userPath);
        File::delete($createRolePath);
        File::delete($deleteRolePath);
        File::delete($syncRolePath);
        File::delete($addPermissionPath);
        File::delete($removePermissionPath);
        File::deleteDirectory(app_path('Actions'));

        foreach (File::glob(database_path('migrations/*_create_access_table.php')) as $migrationPath) {
            File::delete($migrationPath);
        }
    }
});
