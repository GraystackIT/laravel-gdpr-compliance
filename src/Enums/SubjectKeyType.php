<?php

declare(strict_types=1);

namespace GraystackIt\Gdpr\Enums;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Grammar;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\MySqlGrammar;
use Illuminate\Database\Query\Grammars\SqlServerGrammar;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;
use Illuminate\Database\Schema\Grammars\PostgresGrammar;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/**
 * Column type of `subject_id` in the gdpr_* tables.
 *
 * Subjects are stored as a plain morph tuple rather than a foreign key, so the
 * package cannot infer the key type from the subject model — it has to be told.
 * `String` is the only value that can hold numeric and non-numeric keys side by
 * side, which is what an application needs as soon as a second subject model
 * with a different key type is registered.
 */
enum SubjectKeyType: string
{
    case BigInteger = 'bigint';
    case Uuid = 'uuid';
    case Ulid = 'ulid';
    case String = 'string';

    /**
     * Length of the varchar used by the String case. Fits a UUID (36),
     * a ULID (26) and any 64-bit integer key.
     */
    public const StringLength = 64;

    public static function configured(): self
    {
        $value = config('gdpr.subject_key_type', self::BigInteger->value);

        if (! is_string($value) || self::tryFrom($value) === null) {
            throw new InvalidArgumentException(sprintf(
                'Invalid config("gdpr.subject_key_type") [%s]. Expected one of: %s.',
                is_scalar($value) ? (string) $value : get_debug_type($value),
                implode(', ', array_column(self::cases(), 'value')),
            ));
        }

        return self::from($value);
    }

    /**
     * The subject's primary key, coerced to match the configured column type.
     */
    public static function of(Model $subject): int|string
    {
        return self::configured()->cast($subject->getKey());
    }

    public function cast(int|string $key): int|string
    {
        return $this === self::BigInteger ? $key : (string) $key;
    }

    /**
     * Define the `subject_id` column on a schema blueprint.
     */
    public function column(Blueprint $table, string $name = 'subject_id'): ColumnDefinition
    {
        return match ($this) {
            self::BigInteger => $table->unsignedBigInteger($name),
            self::Uuid => $table->uuid($name),
            self::Ulid => $table->ulid($name),
            self::String => $table->string($name, self::StringLength),
        };
    }

    /**
     * Change an existing `subject_id` column to this type.
     *
     * PostgreSQL reinterprets a column only when it is told how: it offers no
     * assignment cast from a string type back to bigint or uuid, and
     * ColumnDefinition::change() never emits a USING clause. The other drivers
     * convert the stored values themselves.
     */
    public function changeColumn(string $table, string $name = 'subject_id'): void
    {
        $connection = Schema::getConnection();
        $grammar = $connection->getSchemaGrammar();

        if (! $grammar instanceof PostgresGrammar) {
            Schema::table($table, function (Blueprint $blueprint) use ($name) {
                $this->column($blueprint, $name)->change();
            });

            return;
        }

        $column = $grammar->wrap($name);

        $connection->statement(sprintf(
            'alter table %s alter column %s type %s using %s',
            $grammar->wrapTable($table),
            $column,
            $this->postgresType(),
            $this->postgresCastOf($column),
        ));
    }

    /**
     * Constrain a query to the rows whose `subject_id` matches a subject's key
     * column. Only a bigint installation holds the key in a column of the same
     * type as the subject's own key; every other type holds it as text, which
     * no database compares to a bigint or uuid key on its own.
     */
    public function whereSubjectIdMatchesKey(Builder $query, string $subjectIdColumn, string $keyColumn): void
    {
        if ($this === self::BigInteger) {
            // Same type on both sides — compared directly, so the index on
            // subject_id stays usable.
            $query->whereColumn($subjectIdColumn, $keyColumn);

            return;
        }

        $grammar = $query->getGrammar();
        $cast = self::textCastFor($grammar);

        $query->whereRaw(sprintf(
            'cast(%s as %s) = cast(%s as %s)',
            $grammar->wrap($subjectIdColumn),
            $cast,
            $grammar->wrap($keyColumn),
            $cast,
        ));
    }

    /**
     * The type the driver spells "text" as in a CAST.
     */
    protected static function textCastFor(Grammar $grammar): string
    {
        return match (true) {
            $grammar instanceof MySqlGrammar => 'char',
            $grammar instanceof SqlServerGrammar => 'nvarchar('.self::StringLength.')',
            default => 'text',
        };
    }

    protected function postgresType(): string
    {
        return match ($this) {
            self::BigInteger => 'bigint',
            self::Uuid => 'uuid',
            self::Ulid => 'char(26)',
            self::String => 'varchar('.self::StringLength.')',
        };
    }

    /**
     * Text is the one conversion PostgreSQL offers between every pair of the
     * four column types, so the USING clause always routes through it.
     */
    protected function postgresCastOf(string $column): string
    {
        return match ($this) {
            self::BigInteger => $column.'::text::bigint',
            self::Uuid => $column.'::text::uuid',
            self::Ulid, self::String => $column.'::text',
        };
    }
}
