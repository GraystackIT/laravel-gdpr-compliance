<?php

declare(strict_types=1);

namespace GraystackIt\Gdpr\Tests\Support;

use GraystackIt\Gdpr\Enums\DeletionState;
use GraystackIt\Gdpr\Enums\RequestStatus;
use GraystackIt\Gdpr\Enums\RequestType;
use GraystackIt\Gdpr\Enums\RetentionMode;
use GraystackIt\Gdpr\Enums\SubjectKeyType;
use GraystackIt\Gdpr\Models\Consent;
use GraystackIt\Gdpr\Models\GdprDeletion;
use GraystackIt\Gdpr\Models\GdprRequest;
use GraystackIt\Gdpr\Support\RetentionPolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Workbench\App\Models\Applicant;
use Workbench\App\Models\User;
use Workbench\App\Models\Visitor;

/**
 * The subject_id scenarios every supported driver has to pass. Kept out of the
 * test files so SQLite, PostgreSQL and MySQL assert exactly the same things.
 */
final class SubjectIdScenarios
{
    /** @var list<string> */
    public const Tables = [
        'gdpr_consents',
        'gdpr_requests',
        'gdpr_deletions',
        'gdpr_audits',
        'gdpr_policy_acceptances',
    ];

    /**
     * whereDeletionPending() / whereNotDeletionPending() compare subject_id to
     * the subject's key column. Both are only the same SQL type for a bigint
     * installation — every other key type has to be compared across types.
     */
    public static function assertDeletionPendingScopes(string $keyType): void
    {
        self::useSubjectKeyType($keyType);

        foreach (self::subjectClassesFor($keyType) as $class) {
            $pending = $class::create(['name' => 'Ada Lovelace', 'email' => 'ada@example.com']);
            $untouched = $class::create(['name' => 'Alan Turing', 'email' => 'alan@example.com']);

            self::schedulePendingDeletion($pending);

            $key = $pending->getKeyName();

            expect($class::whereDeletionPending()->pluck($key)->all())->toBe([$pending->getKey()])
                ->and($class::whereNotDeletionPending()->pluck($key)->all())->toBe([$untouched->getKey()]);
        }
    }

    /**
     * The upgrade migration has to convert the columns in both directions —
     * a rollback nobody can run is not a rollback.
     */
    public static function assertUpgradeMigrationRoundTrip(string $keyType): void
    {
        $driver = DB::connection()->getDriverName();

        self::useSubjectKeyType($keyType);

        foreach (self::Tables as $table) {
            expect(Schema::getColumnType($table, 'subject_id'))
                ->toBe(self::columnTypeFor($driver, $keyType), $table);
        }

        self::upgradeMigration()->down();

        foreach (self::Tables as $table) {
            expect(Schema::getColumnType($table, 'subject_id'))
                ->toBe(self::columnTypeFor($driver, SubjectKeyType::BigInteger->value), $table);
        }
    }

    /**
     * Widening a populated installation to string and rolling it back again.
     */
    public static function assertUpgradeMigrationKeepsStoredKeys(): void
    {
        $driver = DB::connection()->getDriverName();

        Consent::create([
            'subject_type' => User::class,
            'subject_id' => 42,
            'purpose' => 'marketing',
            'action' => 'grant',
        ]);

        self::useSubjectKeyType(SubjectKeyType::String->value);

        expect(Consent::first()->subject_id)->toBe('42');

        self::upgradeMigration()->down();

        expect(Schema::getColumnType('gdpr_consents', 'subject_id'))
            ->toBe(self::columnTypeFor($driver, SubjectKeyType::BigInteger->value))
            ->and((int) Consent::first()->subject_id)->toBe(42);
    }

    /**
     * Point the package at a subject key type and bring the already migrated
     * columns in line with it, the way an existing installation would.
     */
    protected static function useSubjectKeyType(string $keyType): void
    {
        config()->set('gdpr.subject_key_type', $keyType);

        if ($keyType !== SubjectKeyType::BigInteger->value) {
            self::upgradeMigration()->up();
        }
    }

    /**
     * @return list<class-string<Model>>
     */
    protected static function subjectClassesFor(string $keyType): array
    {
        return match (SubjectKeyType::from($keyType)) {
            SubjectKeyType::BigInteger => [User::class],
            SubjectKeyType::Uuid => [Applicant::class],
            SubjectKeyType::Ulid => [Visitor::class],
            SubjectKeyType::String => [User::class, Applicant::class, Visitor::class],
        };
    }

    protected static function schedulePendingDeletion(Model $subject): GdprDeletion
    {
        $request = GdprRequest::create([
            'subject_type' => $subject::class,
            'subject_id' => SubjectKeyType::of($subject),
            'type' => RequestType::Delete,
            'status' => RequestStatus::Pending,
            'requested_at' => now(),
        ]);

        return GdprDeletion::create([
            'gdpr_request_id' => $request->id,
            'subject_type' => $subject::class,
            'subject_id' => SubjectKeyType::of($subject),
            'target_model' => $subject::class,
            'retention_snapshot' => (new RetentionPolicy(RetentionMode::Delete, 7, null, null))->toSnapshot(),
            'state' => DeletionState::PendingGrace,
            'process_order' => 1000,
            'scheduled_for' => now()->addDays(7),
        ]);
    }

    /**
     * What each driver actually stores for a subject key type.
     */
    protected static function columnTypeFor(string $driver, string $keyType): string
    {
        return match ($driver) {
            'pgsql' => match (SubjectKeyType::from($keyType)) {
                SubjectKeyType::BigInteger => 'int8',
                SubjectKeyType::Uuid => 'uuid',
                SubjectKeyType::Ulid => 'bpchar',
                SubjectKeyType::String => 'varchar',
            },
            'mysql', 'mariadb' => match (SubjectKeyType::from($keyType)) {
                SubjectKeyType::BigInteger => 'bigint',
                SubjectKeyType::Uuid, SubjectKeyType::Ulid => 'char',
                SubjectKeyType::String => 'varchar',
            },
            default => SubjectKeyType::from($keyType) === SubjectKeyType::BigInteger ? 'integer' : 'varchar',
        };
    }

    protected static function upgradeMigration(): object
    {
        return require __DIR__.'/../../database/upgrades/2026_09_11_000000_change_gdpr_subject_id_type.php';
    }
}
