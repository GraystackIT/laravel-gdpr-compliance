<?php

declare(strict_types=1);

use GraystackIt\Gdpr\Enums\SubjectKeyType;
use Illuminate\Database\MySqlConnection;
use Illuminate\Database\PostgresConnection;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Database\SqlServerConnection;
use Workbench\App\Models\User;

it('defaults to bigint', function () {
    expect(SubjectKeyType::configured())->toBe(SubjectKeyType::BigInteger);
});

it('rejects an unknown configured value', function () {
    config()->set('gdpr.subject_key_type', 'integer');

    SubjectKeyType::configured();
})->throws(InvalidArgumentException::class, 'Invalid config("gdpr.subject_key_type") [integer]');

it('leaves keys untouched for bigint and stringifies them otherwise', function () {
    expect(SubjectKeyType::BigInteger->cast(42))->toBe(42)
        ->and(SubjectKeyType::Uuid->cast(42))->toBe('42')
        ->and(SubjectKeyType::Ulid->cast(42))->toBe('42')
        ->and(SubjectKeyType::String->cast(42))->toBe('42');
});

it('casts a subject key to the configured type', function () {
    $user = new User(['name' => 'Ada']);
    $user->id = 7;

    expect(SubjectKeyType::of($user))->toBe(7);

    config()->set('gdpr.subject_key_type', 'string');

    expect(SubjectKeyType::of($user))->toBe('7');
});

/**
 * A query builder on a driver's grammar, without a server behind it.
 */
function queryOn(string $driver): Builder
{
    $connection = match ($driver) {
        'pgsql' => new PostgresConnection(fn () => null),
        'mysql' => new MySqlConnection(fn () => null),
        'sqlsrv' => new SqlServerConnection(fn () => null),
        default => new SQLiteConnection(fn () => null),
    };

    return $connection->query()->from('gdpr_deletions');
}

it('compares subject_id to the subject key column without a cast for bigint', function (string $driver) {
    $query = queryOn($driver);

    SubjectKeyType::BigInteger->whereSubjectIdMatchesKey($query, 'gdpr_deletions.subject_id', 'users.id');

    expect($query->toSql())->not->toContain('cast');
})->with(['sqlite', 'pgsql', 'mysql', 'sqlsrv']);

it('casts both sides for every key type a database cannot compare on its own', function (string $driver, string $expected) {
    foreach ([SubjectKeyType::Uuid, SubjectKeyType::Ulid, SubjectKeyType::String] as $type) {
        $query = queryOn($driver);

        $type->whereSubjectIdMatchesKey($query, 'gdpr_deletions.subject_id', 'users.id');

        expect($query->toSql())->toContain($expected);
    }
})->with([
    ['sqlite', 'cast("gdpr_deletions"."subject_id" as text) = cast("users"."id" as text)'],
    ['pgsql', 'cast("gdpr_deletions"."subject_id" as text) = cast("users"."id" as text)'],
    ['mysql', 'cast(`gdpr_deletions`.`subject_id` as char) = cast(`users`.`id` as char)'],
    ['sqlsrv', 'cast([gdpr_deletions].[subject_id] as nvarchar(64)) = cast([users].[id] as nvarchar(64))'],
]);
