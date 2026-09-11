<?php

declare(strict_types=1);

use GraystackIt\Gdpr\Enums\SubjectKeyType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($type) {
                $type->column($blueprint)->change();
            });
        }
    }
};
