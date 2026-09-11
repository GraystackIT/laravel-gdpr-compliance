<?php

declare(strict_types=1);

use GraystackIt\Gdpr\Enums\SubjectKeyType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Brings the subject_id columns of an existing installation in line with
 * config('gdpr.subject_key_type').
 *
 * Run this after switching the config away from 'bigint'. It is a no-op when
 * the columns already have the configured type, so it is safe to keep in the
 * migration history of a fresh installation.
 */
return new class extends Migration
{
    /**
     * @var list<string>
     */
    protected array $tables = [
        'gdpr_consents',
        'gdpr_requests',
        'gdpr_deletions',
        'gdpr_audits',
        'gdpr_policy_acceptances',
    ];

    public function up(): void
    {
        $this->changeSubjectIdTo(SubjectKeyType::configured());
    }

    public function down(): void
    {
        $this->changeSubjectIdTo(SubjectKeyType::BigInteger);
    }

    protected function changeSubjectIdTo(SubjectKeyType $type): void
    {
        $tables = array_filter($this->tables, fn (string $table) => Schema::hasTable($table));

        // Check every table before altering any of them, so a subject key the
        // target type cannot hold leaves the schema untouched rather than
        // half-converted.
        foreach ($tables as $table) {
            $this->assertStoredKeysFit($table, $type);
        }

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($type) {
                $type->column($blueprint)->change();
            });
        }
    }

    /**
     * Stored subject_id values are application data this migration cannot
     * rewrite — it has no way to know which UUID a subject that used to have
     * the key 42 now carries. Refuse the conversion instead of truncating the
     * values (MySQL) or failing halfway through the ALTER (PostgreSQL).
     */
    protected function assertStoredKeysFit(string $table, SubjectKeyType $type): void
    {
        // varchar(64) holds every key shape the other cases produce.
        if ($type === SubjectKeyType::String) {
            return;
        }

        foreach (DB::table($table)->distinct()->select('subject_id')->cursor() as $row) {
            if ($this->fits((string) $row->subject_id, $type)) {
                continue;
            }

            throw new RuntimeException(sprintf(
                '%s.subject_id holds [%s], which is not a valid %s. Map the stored subject keys '
                .'to their new values first, or set config("gdpr.subject_key_type") to "string", '
                .'which holds both shapes.',
                $table,
                $row->subject_id,
                $type->value,
            ));
        }
    }

    protected function fits(string $key, SubjectKeyType $type): bool
    {
        return match ($type) {
            SubjectKeyType::BigInteger => ctype_digit($key),
            SubjectKeyType::Uuid => (bool) preg_match('/^[0-9a-f]{8}(-[0-9a-f]{4}){3}-[0-9a-f]{12}$/i', $key),
            SubjectKeyType::Ulid => (bool) preg_match('/^[0-7][0-9ABCDEFGHJKMNPQRSTVWXYZ]{25}$/i', $key),
            SubjectKeyType::String => true,
        };
    }
};
