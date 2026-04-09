<?php

declare(strict_types=1);

namespace GraystackIt\Gdpr\Tests;

use GraystackIt\Gdpr\Anonymizers\AddressAnonymizer;
use GraystackIt\Gdpr\Anonymizers\EmailAnonymizer;
use GraystackIt\Gdpr\Anonymizers\FreeTextAnonymizer;
use GraystackIt\Gdpr\Anonymizers\IpAddressAnonymizer;
use GraystackIt\Gdpr\Anonymizers\NameAnonymizer;
use GraystackIt\Gdpr\Anonymizers\PhoneAnonymizer;
use GraystackIt\Gdpr\Anonymizers\StaticTextAnonymizer;
use GraystackIt\Gdpr\GdprServiceProvider;
use GraystackIt\Gdpr\Support\AnonymizerManager;
use GraystackIt\Gdpr\Support\AuditLogger;
use GraystackIt\Gdpr\Support\DeletionScheduler;
use GraystackIt\Gdpr\Support\ModelRegistry;
use GraystackIt\Gdpr\Support\PersonalDataEraser;
use GraystackIt\Gdpr\Support\PersonalDataExporter;
use GraystackIt\Gdpr\Support\SubjectRecordResolver;
use Orchestra\Testbench\TestCase as Orchestra;
use Workbench\App\Models\Address;
use Workbench\App\Models\Order;
use Workbench\App\Models\User;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            GdprServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        $app['config']->set('gdpr.models', [
            User::class,
            Order::class,
            Address::class,
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadMigrationsFrom(__DIR__.'/../workbench/database/migrations');
    }

    protected function bindGdprServices(): void
    {
        $this->app->singleton(
            ModelRegistry::class,
            fn () => new ModelRegistry($this->app['config']->get('gdpr.models', []))
        );

        $this->app->singleton(AnonymizerManager::class, fn () => new AnonymizerManager([
            'name' => NameAnonymizer::class,
            'email' => EmailAnonymizer::class,
            'phone' => PhoneAnonymizer::class,
            'ip_address' => IpAddressAnonymizer::class,
            'address' => AddressAnonymizer::class,
            'free_text' => FreeTextAnonymizer::class,
            'static_text' => StaticTextAnonymizer::class,
        ]));

        $this->app->singleton(
            SubjectRecordResolver::class,
            fn ($app) => new SubjectRecordResolver($app->make(ModelRegistry::class))
        );

        $this->app->singleton(
            PersonalDataEraser::class,
            fn ($app) => new PersonalDataEraser(
                $app->make(ModelRegistry::class),
                $app->make(AnonymizerManager::class),
            )
        );

        $this->app->singleton(
            PersonalDataExporter::class,
            fn ($app) => new PersonalDataExporter(
                $app->make(ModelRegistry::class),
                $app->make(SubjectRecordResolver::class),
            )
        );

        $this->app->singleton(AuditLogger::class, fn () => new AuditLogger);

        $this->app->singleton(
            DeletionScheduler::class,
            fn ($app) => new DeletionScheduler(
                $app->make(ModelRegistry::class),
                $app->make(SubjectRecordResolver::class),
                $app->make(PersonalDataEraser::class),
                $app->make(AuditLogger::class),
            )
        );
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->bindGdprServices();
    }
}
