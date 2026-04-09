<?php

declare(strict_types=1);

namespace GraystackIt\Gdpr;

use GraystackIt\Gdpr\Commands\GdprAuditCommand;
use GraystackIt\Gdpr\Commands\GdprCleanupExportsCommand;
use GraystackIt\Gdpr\Commands\GdprEraseCommand;
use GraystackIt\Gdpr\Commands\GdprExportCommand;
use GraystackIt\Gdpr\Commands\GdprPackagesScanCommand;
use GraystackIt\Gdpr\Commands\GdprProcessDeletionsCommand;
use GraystackIt\Gdpr\Commands\GdprPruneCommand;
use GraystackIt\Gdpr\Commands\GdprReportCommand;
use GraystackIt\Gdpr\Middleware\ApplyConsentCookies;
use GraystackIt\Gdpr\Middleware\RequireConsent;
use GraystackIt\Gdpr\Middleware\RequireNoDeletionPending;
use GraystackIt\Gdpr\Support\AnonymizerManager;
use GraystackIt\Gdpr\Support\AuditLogger;
use GraystackIt\Gdpr\Support\ConsentCookieManager;
use GraystackIt\Gdpr\Support\ConsentManager;
use GraystackIt\Gdpr\Support\DeletionScheduler;
use GraystackIt\Gdpr\Support\GdprManager;
use GraystackIt\Gdpr\Support\ModelRegistry;
use GraystackIt\Gdpr\Support\PackageInventoryScanner;
use GraystackIt\Gdpr\Support\PersonalDataEraser;
use GraystackIt\Gdpr\Support\PersonalDataExporter;
use GraystackIt\Gdpr\Support\PolicyLinkManager;
use GraystackIt\Gdpr\Support\SubjectRecordResolver;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

class GdprServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/gdpr.php', 'gdpr');

        $this->app->singleton(ModelRegistry::class, function ($app) {
            return new ModelRegistry($app['config']->get('gdpr.models', []));
        });

        $this->app->singleton(AnonymizerManager::class, function ($app) {
            return new AnonymizerManager($app['config']->get('gdpr.anonymizers', []));
        });

        $this->app->singleton(SubjectRecordResolver::class, function ($app) {
            return new SubjectRecordResolver($app->make(ModelRegistry::class));
        });

        $this->app->singleton(PersonalDataEraser::class, function ($app) {
            return new PersonalDataEraser(
                $app->make(ModelRegistry::class),
                $app->make(AnonymizerManager::class),
            );
        });

        $this->app->singleton(PersonalDataExporter::class, function ($app) {
            return new PersonalDataExporter(
                $app->make(ModelRegistry::class),
                $app->make(SubjectRecordResolver::class),
            );
        });

        $this->app->singleton(AuditLogger::class);
        $this->app->singleton(ConsentManager::class);

        $this->app->singleton(ConsentCookieManager::class);

        $this->app->singleton(PolicyLinkManager::class, function ($app) {
            return new PolicyLinkManager($app['config']->get('gdpr.policies', []));
        });

        $this->app->singleton(DeletionScheduler::class, function ($app) {
            return new DeletionScheduler(
                $app->make(ModelRegistry::class),
                $app->make(SubjectRecordResolver::class),
                $app->make(PersonalDataEraser::class),
                $app->make(AuditLogger::class),
            );
        });

        $this->app->singleton(PackageInventoryScanner::class, function ($app) {
            return new PackageInventoryScanner(
                basePath: base_path(),
                outputDisk: $app['config']->get('gdpr.inventory.disk', 'local'),
                outputPath: $app['config']->get('gdpr.inventory.path', 'gdpr/package-inventory.json'),
            );
        });

        $this->app->singleton(GdprManager::class, function ($app) {
            return new GdprManager(
                $app->make(DeletionScheduler::class),
                $app->make(PersonalDataExporter::class),
                $app->make(PackageInventoryScanner::class),
            );
        });
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'gdpr');

        $this->registerMiddleware();

        if ($this->app->runningInConsole()) {
            $this->registerCommands();
            $this->registerPublishing();
        }
    }

    protected function registerMiddleware(): void
    {
        /** @var Router $router */
        $router = $this->app->make(Router::class);

        $router->aliasMiddleware('gdpr.consent', RequireConsent::class);
        $router->aliasMiddleware('gdpr.consent-cookies', ApplyConsentCookies::class);
        $router->aliasMiddleware('gdpr.no-deletion-pending', RequireNoDeletionPending::class);
    }

    protected function registerCommands(): void
    {
        $this->commands([
            GdprProcessDeletionsCommand::class,
            GdprExportCommand::class,
            GdprEraseCommand::class,
            GdprAuditCommand::class,
            GdprReportCommand::class,
            GdprPackagesScanCommand::class,
            GdprCleanupExportsCommand::class,
            GdprPruneCommand::class,
        ]);
    }

    protected function registerPublishing(): void
    {
        $this->publishes([
            __DIR__.'/../config/gdpr.php' => config_path('gdpr.php'),
        ], ['gdpr-config', 'gdpr']);

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], ['gdpr-migrations', 'gdpr']);

        $this->publishes([
            __DIR__.'/../lang' => $this->app->langPath('vendor/gdpr'),
        ], ['gdpr-lang', 'gdpr']);

        // Notification classes (for deep customization)
        $this->publishes([
            __DIR__.'/Notifications' => app_path('Notifications'),
        ], ['gdpr-notifications', 'gdpr']);
    }
}
