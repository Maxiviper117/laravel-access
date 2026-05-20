<?php

namespace Maxiviper117\Access;

use Illuminate\Support\Facades\Gate;
use Maxiviper117\Access\Commands\ClearAccessCommand;
use Maxiviper117\Access\Commands\DebugAccessCommand;
use Maxiviper117\Access\Commands\InstallAccessCommand;
use Maxiviper117\Access\Commands\ScopeAccessCommand;
use Maxiviper117\Access\Commands\SyncAccessCommand;
use Maxiviper117\Access\Middleware\EnsureHasPermission;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class AccessServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-access')
            ->hasConfigFile('access')
            ->hasMigration('create_access_table')
            ->hasCommands([
                InstallAccessCommand::class,
                ScopeAccessCommand::class,
                SyncAccessCommand::class,
                ClearAccessCommand::class,
                DebugAccessCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(Access::class);
        $this->app->alias(Access::class, 'access');
    }

    public function packageBooted(): void
    {
        $this->app->make('router')->aliasMiddleware('access', EnsureHasPermission::class);

        if (config('access.gate_before.enabled')) {
            Gate::before(function (object $user): ?bool {
                $role = config('access.gate_before.global_role');

                return method_exists($user, 'hasGlobalRole') && $user->hasGlobalRole($role) ? true : null;
            });
        }
    }
}
