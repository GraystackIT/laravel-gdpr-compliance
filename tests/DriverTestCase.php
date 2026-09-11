<?php

declare(strict_types=1);

namespace GraystackIt\Gdpr\Tests;

use GraystackIt\Gdpr\Tests\Support\TestDatabase;

/**
 * Runs the package against a real database server instead of SQLite. Skipped
 * when no server for the driver is reachable.
 */
abstract class DriverTestCase extends TestCase
{
    protected string $driver = 'pgsql';

    protected function setUp(): void
    {
        if (! TestDatabase::available($this->driver)) {
            $this->markTestSkipped(sprintf(
                'No reachable %s server — set GDPR_%s_HOST/PORT/USERNAME/PASSWORD to point at one.',
                $this->driver,
                strtoupper($this->driver),
            ));
        }

        parent::setUp();
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('database.connections.testing', TestDatabase::config($this->driver));
    }
}
