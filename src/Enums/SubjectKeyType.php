<?php

declare(strict_types=1);

namespace GraystackIt\Gdpr\Enums;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;
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
}
