<?php

declare(strict_types=1);

use GraystackIt\Gdpr\Tests\GlobalScopedSubjectTestCase;
use GraystackIt\Gdpr\Tests\MySqlTestCase;
use GraystackIt\Gdpr\Tests\PostgresTestCase;
use GraystackIt\Gdpr\Tests\StringSubjectKeyTestCase;
use GraystackIt\Gdpr\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(StringSubjectKeyTestCase::class)
    ->use(RefreshDatabase::class)
    ->in('SubjectKeyTypes');

pest()->extend(GlobalScopedSubjectTestCase::class)
    ->use(RefreshDatabase::class)
    ->in('GlobalScopes');

// The driver suites deliberately run without RefreshDatabase: their tests
// ALTER column types, and MySQL commits DDL implicitly, so a transaction could
// not roll those changes back. Testbench migrates and rolls back around every
// test instead, which resets the schema on each driver.
pest()->extend(PostgresTestCase::class)->in('Drivers/Postgres');

pest()->extend(MySqlTestCase::class)->in('Drivers/MySql');

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Drivers/Sqlite');

pest()->extend(TestCase::class)->in('Unit');
