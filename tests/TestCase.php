<?php

declare(strict_types=1);

namespace GraystackIt\Gdpr\Tests;

use GraystackIt\Gdpr\GdprServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Workbench\App\Models\Address;
use Workbench\App\Models\Order;
use Workbench\App\Models\User;

abstract class TestCase extends Orchestra
{
    protected string $subjectKeyType = 'bigint';

    protected function getPackageProviders($app): array
    {
        return [
            GdprServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        $app['config']->set('gdpr.subject_key_type', $this->subjectKeyType);
        $app['config']->set('gdpr.models', $this->registeredModels());
    }

    /**
     * @return list<class-string>
     */
    protected function registeredModels(): array
    {
        return [
            User::class,
            Order::class,
            Address::class,
        ];
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadMigrationsFrom(__DIR__.'/../workbench/database/migrations');
    }
}
