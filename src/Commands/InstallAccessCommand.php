<?php

namespace Maxiviper117\Access\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class InstallAccessCommand extends Command
{
    protected $signature = 'access:install {--enum : Generate app/Enums/Permission.php if it does not exist}';

    protected $description = 'Publish Laravel Access config, migrations, and optional permission enum.';

    public function handle(Filesystem $files): int
    {
        $this->call('vendor:publish', [
            '--tag' => 'access-config',
            '--force' => true,
        ]);

        $this->call('vendor:publish', [
            '--tag' => 'access-migrations',
            '--force' => true,
        ]);

        if ($this->option('enum')) {
            $path = app_path('Enums/Permission.php');

            if (! $files->exists($path)) {
                $files->ensureDirectoryExists(dirname($path));
                $files->copy(__DIR__.'/../../resources/stubs/PermissionEnum.stub', $path);
                $this->info('Generated app/Enums/Permission.php.');
            }

            $this->patchPermissionEnumsConfig($files);
        }

        $this->insertHasAccessTraitIntoUserModel($files);

        $this->line('Next steps: configure config/access.php, then run php artisan migrate && php artisan access:sync.');

        return self::SUCCESS;
    }

    private function insertHasAccessTraitIntoUserModel(Filesystem $files): void
    {
        $userModelPath = app_path('Models/User.php');

        if (! $files->exists($userModelPath)) {
            $userModelPath = app_path('User.php');
        }

        if (! $files->exists($userModelPath)) {
            $this->warn('Could not find User model at app/Models/User.php or app/User.php. Please add Maxiviper117\\Access\\Concerns\\HasAccess to your User model manually.');

            return;
        }

        $contents = $files->get($userModelPath);

        if (str_contains($contents, 'HasAccess')) {
            $this->info('User model already has HasAccess trait.');

            return;
        }

        // Add class import
        $import = "use Maxiviper117\Access\Concerns\HasAccess;";
        if (preg_match('/namespace [^;]+;/', $contents, $matches)) {
            $contents = str_replace($matches[0], $matches[0]."\n\n".$import, $contents);
        } else {
            $contents = "<?php\n\n".$import."\n".ltrim(substr($contents, 5));
        }

        // Add "use HasAccess;" inside the class definition
        if (preg_match('/class User\s+(extends\s+[^{]+)?\s*\{/', $contents, $matches)) {
            $contents = str_replace($matches[0], $matches[0]."\n    use HasAccess;", $contents);
        } else {
            $this->warn('Failed to locate class User inside the User model file. Please add HasAccess trait manually.');

            return;
        }

        $files->put($userModelPath, $contents);
        $this->info('Added HasAccess trait to User model.');
    }

    private function patchPermissionEnumsConfig(Filesystem $files): void
    {
        $path = config_path('access.php');

        if (! $files->exists($path)) {
            return;
        }

        $contents = $files->get($path);
        $patched = preg_replace(
            "/    'permission_enums' => \\[\\],\\R/",
            "    'permission_enums' => [\\App\\Enums\\Permission::class],\n",
            $contents,
            1
        );

        if ($patched === null || $patched === $contents) {
            return;
        }

        $files->put($path, $patched);
        $this->info('Updated config/access.php permission_enums.');
    }
}
