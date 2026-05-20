<?php

namespace Maxiviper117\Access\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Maxiviper117\Access\AccessServiceProvider;
use Maxiviper117\Access\Tests\Fixtures\User;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName): string => 'Maxiviper117\\Access\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app)
    {
        return [
            AccessServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        config()->set('access.user_model', User::class);
        config()->set('access.cache.enabled', false);

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->unique();
            $table->timestamps();
        });

        Schema::create('companies', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        (include __DIR__.'/../database/migrations/create_access_table.php.stub')->up();
    }
}
