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
            '--tag' => 'laravel-access-config',
            '--force' => true,
        ]);

        $this->call('vendor:publish', [
            '--tag' => 'laravel-access-migrations',
            '--force' => true,
        ]);

        if ($this->option('enum')) {
            $path = app_path('Enums/Permission.php');

            if (! $files->exists($path)) {
                $files->ensureDirectoryExists(dirname($path));
                $files->copy(__DIR__.'/../../resources/stubs/PermissionEnum.stub', $path);
                $this->info('Generated app/Enums/Permission.php.');
            }
        }

        $this->line('Next steps: add Maxiviper117\\Access\\Concerns\\HasAccess to your User model, configure config/access.php, then run php artisan migrate && php artisan access:sync.');

        return self::SUCCESS;
    }
}
