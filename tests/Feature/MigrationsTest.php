<?php

declare(strict_types=1);

use GraystackIt\Gdpr\Enums\DeletionState;
use GraystackIt\Gdpr\Enums\RequestStatus;
use GraystackIt\Gdpr\Enums\RequestType;
use GraystackIt\Gdpr\Enums\RetentionMode;
use GraystackIt\Gdpr\Models\Consent;
use GraystackIt\Gdpr\Models\GdprAudit;
use GraystackIt\Gdpr\Models\GdprDeletion;
use GraystackIt\Gdpr\Models\GdprPolicyAcceptance;
use GraystackIt\Gdpr\Models\GdprPolicyVersion;
use GraystackIt\Gdpr\Models\GdprRequest;
use GraystackIt\Gdpr\Support\RetentionPolicy;
use Illuminate\Support\Facades\Schema;

it('creates all GDPR tables', function () {
    expect(Schema::hasTable('consents'))->toBeTrue()
        ->and(Schema::hasTable('gdpr_requests'))->toBeTrue()
        ->and(Schema::hasTable('gdpr_deletions'))->toBeTrue()
        ->and(Schema::hasTable('gdpr_audits'))->toBeTrue()
        ->and(Schema::hasTable('gdpr_policy_versions'))->toBeTrue()
        ->and(Schema::hasTable('gdpr_policy_acceptances'))->toBeTrue();
});

it('persists a consent record with json context', function () {
    $consent = Consent::create([
        'subject_type' => 'App\\Models\\User',
        'subject_id' => 42,
        'purpose' => 'marketing',
        'action' => 'grant',
        'source' => 'cookie_banner',
        'context' => ['policy_version' => '2026-04', 'truncated_ip' => '10.0.0.0'],
    ]);

    expect($consent->fresh()->context)
        ->toBe(['policy_version' => '2026-04', 'truncated_ip' => '10.0.0.0']);
});

it('persists a GdprRequest with enum casts', function () {
    $request = GdprRequest::create([
        'subject_type' => 'App\\Models\\User',
        'subject_id' => 1,
        'type' => RequestType::Delete,
        'status' => RequestStatus::Pending,
        'notification_email' => 'user@example.com',
        'requested_at' => now(),
    ]);

    expect($request->fresh()->type)->toBe(RequestType::Delete)
        ->and($request->fresh()->status)->toBe(RequestStatus::Pending)
        ->and($request->isTerminal())->toBeFalse();
});

it('persists a GdprDeletion and transitions states', function () {
    $request = GdprRequest::create([
        'subject_type' => 'App\\Models\\User',
        'subject_id' => 1,
        'type' => RequestType::Delete,
        'status' => RequestStatus::Pending,
        'requested_at' => now(),
    ]);

    $snapshot = (new RetentionPolicy(RetentionMode::Delete, 7, null, null))->toSnapshot();

    $deletion = GdprDeletion::create([
        'gdpr_request_id' => $request->id,
        'subject_type' => 'App\\Models\\User',
        'subject_id' => 1,
        'target_model' => 'App\\Models\\User',
        'retention_snapshot' => $snapshot,
        'state' => DeletionState::PendingGrace,
        'process_order' => 1000,
        'scheduled_for' => now()->addDays(7),
    ]);

    expect($deletion->fresh()->state)->toBe(DeletionState::PendingGrace)
        ->and($deletion->fresh()->retentionPolicy()->mode)->toBe(RetentionMode::Delete)
        ->and($deletion->fresh()->process_order)->toBe(1000);

    $deletion->transitionTo(DeletionState::Erased);
    $deletion->save();

    expect($deletion->fresh()->state)->toBe(DeletionState::Erased);
});

it('rejects invalid state transitions', function () {
    $request = GdprRequest::create([
        'subject_type' => 'App\\Models\\User',
        'subject_id' => 1,
        'type' => RequestType::Delete,
        'status' => RequestStatus::Pending,
        'requested_at' => now(),
    ]);

    $snapshot = (new RetentionPolicy(RetentionMode::Delete, 0, null, null))->toSnapshot();

    $deletion = GdprDeletion::create([
        'gdpr_request_id' => $request->id,
        'subject_type' => 'App\\Models\\User',
        'subject_id' => 1,
        'target_model' => 'App\\Models\\User',
        'retention_snapshot' => $snapshot,
        'state' => DeletionState::Erased,
        'process_order' => 100,
        'scheduled_for' => now(),
    ]);

    $deletion->transitionTo(DeletionState::PendingGrace);
})->throws(LogicException::class, 'Cannot transition');

it('persists a GdprAudit with context and no values', function () {
    $audit = GdprAudit::create([
        'subject_type' => 'App\\Models\\User',
        'subject_id' => 42,
        'event' => 'deletion_requested',
        'target_model' => null,
        'affected_rows' => null,
        'context' => ['registered_models' => ['User', 'Order']],
    ]);

    expect($audit->fresh()->event)->toBe('deletion_requested')
        ->and($audit->fresh()->context)->toBe(['registered_models' => ['User', 'Order']]);
});

it('persists policy versions and acceptances', function () {
    $version = GdprPolicyVersion::create([
        'slug' => 'privacy',
        'version' => '2026-04',
        'title' => 'Privacy Policy',
        'url' => 'https://example.com/privacy',
        'published_at' => now(),
    ]);

    $acceptance = GdprPolicyAcceptance::create([
        'gdpr_policy_version_id' => $version->id,
        'subject_type' => 'App\\Models\\User',
        'subject_id' => 1,
        'context' => ['source' => 'login'],
    ]);

    expect($acceptance->policyVersion->slug)->toBe('privacy');
});
