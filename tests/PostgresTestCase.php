<?php

declare(strict_types=1);

namespace GraystackIt\Gdpr\Tests;

abstract class PostgresTestCase extends DriverTestCase
{
    protected string $driver = 'pgsql';
}
