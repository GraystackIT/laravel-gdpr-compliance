<?php

declare(strict_types=1);

namespace GraystackIt\Gdpr\Tests\Support;

use InvalidArgumentException;
use PDO;
use PDOException;

/**
 * Connection settings for the driver-specific test suites.
 *
 * They run against a real PostgreSQL or MySQL server when one is reachable and
 * are skipped otherwise, so a plain `vendor/bin/pest` stays on SQLite. Point
 * them somewhere else with GDPR_PGSQL_* / GDPR_MYSQL_* environment variables.
 * The test database is created on first use.
 */
final class TestDatabase
{
    /** @var array<string, bool> */
    protected static array $available = [];

    /**
     * @return array<string, mixed>
     */
    public static function config(string $driver): array
    {
        return match ($driver) {
            'pgsql' => [
                'driver' => 'pgsql',
                'host' => env('GDPR_PGSQL_HOST', '127.0.0.1'),
                'port' => (int) env('GDPR_PGSQL_PORT', 5432),
                'database' => self::database($driver),
                'username' => env('GDPR_PGSQL_USERNAME', 'postgres'),
                'password' => (string) env('GDPR_PGSQL_PASSWORD', ''),
                'charset' => 'utf8',
                'prefix' => '',
                'search_path' => 'public',
                'sslmode' => 'prefer',
            ],
            'mysql' => [
                'driver' => 'mysql',
                'host' => env('GDPR_MYSQL_HOST', '127.0.0.1'),
                'port' => (int) env('GDPR_MYSQL_PORT', 3306),
                'database' => self::database($driver),
                'username' => env('GDPR_MYSQL_USERNAME', 'root'),
                'password' => (string) env('GDPR_MYSQL_PASSWORD', ''),
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
            ],
            default => throw new InvalidArgumentException("No test connection defined for driver [{$driver}]."),
        };
    }

    /**
     * Whether a server for the driver answers — and, if it does, that the test
     * database exists. Resolved once per process.
     */
    public static function available(string $driver): bool
    {
        return self::$available[$driver] ??= self::ensureDatabaseExists($driver);
    }

    protected static function database(string $driver): string
    {
        $name = (string) env(
            $driver === 'pgsql' ? 'GDPR_PGSQL_DATABASE' : 'GDPR_MYSQL_DATABASE',
            'gdpr_compliance_test',
        );

        if (preg_match('/^[A-Za-z0-9_]+$/', $name) !== 1) {
            throw new InvalidArgumentException("Invalid test database name [{$name}].");
        }

        return $name;
    }

    protected static function ensureDatabaseExists(string $driver): bool
    {
        $config = self::config($driver);

        try {
            $server = new PDO(
                $driver === 'pgsql'
                    ? sprintf('pgsql:host=%s;port=%d;dbname=postgres', $config['host'], $config['port'])
                    : sprintf('mysql:host=%s;port=%d', $config['host'], $config['port']),
                $config['username'],
                $config['password'],
                [PDO::ATTR_TIMEOUT => 2, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
            );

            if ($driver === 'mysql') {
                $server->exec(sprintf('create database if not exists `%s`', $config['database']));

                return true;
            }

            $exists = $server
                ->query(sprintf('select 1 from pg_database where datname = %s', $server->quote($config['database'])))
                ->fetchColumn();

            if ($exists === false) {
                $server->exec(sprintf('create database "%s"', $config['database']));
            }

            return true;
        } catch (PDOException) {
            return false;
        }
    }
}
