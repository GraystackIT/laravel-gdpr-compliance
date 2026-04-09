<?php

declare(strict_types=1);

use GraystackIt\Gdpr\Models\GdprAudit;
use GraystackIt\Gdpr\Support\AuditLogger;
use Workbench\App\Models\User;

it('writes an audit entry with subject fk', function () {
    $user = User::create(['name' => 'Ada', 'email' => 'ada@example.com']);

    $logger = new AuditLogger;
    $entry = $logger->log(
        event: 'deletion_requested',
        subject: $user,
        context: ['registered_models' => ['User', 'Order']],
    );

    expect($entry->fresh()->event)->toBe('deletion_requested')
        ->and($entry->fresh()->subject_type)->toBe(User::class)
        ->and((int) $entry->fresh()->subject_id)->toBe((int) $user->id)
        ->and($entry->fresh()->context)->toBe(['registered_models' => ['User', 'Order']]);
});

it('strips PII-looking keys from context', function () {
    $logger = new AuditLogger;
    $logger->log(
        event: 'test',
        subjectType: 'App\\User',
        subjectId: 1,
        context: [
            'email' => 'ada@example.com',      // PII — stripped
            'name' => 'Ada',                    // PII — stripped
            'ip_address' => '1.2.3.4',          // PII — stripped
            'field_names' => ['name', 'email'], // metadata — kept
        ],
    );

    $entry = GdprAudit::first();

    expect($entry->context)->toBe(['field_names' => ['name', 'email']]);
});

it('accepts explicit subjectType and subjectId without a model', function () {
    $logger = new AuditLogger;
    $entry = $logger->log(
        event: 'deletion_completed',
        subjectType: 'App\\Orphaned',
        subjectId: 999,
        targetModel: 'App\\Order',
        affectedRows: 12,
    );

    expect($entry->subject_type)->toBe('App\\Orphaned')
        ->and((int) $entry->subject_id)->toBe(999)
        ->and($entry->target_model)->toBe('App\\Order')
        ->and($entry->affected_rows)->toBe(12);
});
