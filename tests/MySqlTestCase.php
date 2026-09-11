<?php

declare(strict_types=1);

namespace GraystackIt\Gdpr\Tests;

abstract class MySqlTestCase extends DriverTestCase
{
    protected string $driver = 'mysql';
}
