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

        $this->line('Next steps: add Maxiviper117\\Access\\Concerns\\HasAccess to your User model, configure config/access.php, then run php artisan migrate && php artisan access:sync.');

        return self::SUCCESS;
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
