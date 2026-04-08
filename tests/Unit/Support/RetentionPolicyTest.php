<?php

declare(strict_types=1);

use GraystackIt\Gdpr\Enums\RetentionMode;
use GraystackIt\Gdpr\Support\RetentionPolicy;

it('reports grace presence and legal hold requirement', function () {
    $delete = new RetentionPolicy(RetentionMode::Delete, 7, null, null);
    expect($delete->hasGrace())->toBeTrue()
        ->and($delete->requiresLegalHold())->toBeFalse();

    $immediate = new RetentionPolicy(RetentionMode::Delete, 0, null, null);
    expect($immediate->hasGrace())->toBeFalse();

    $hold = new RetentionPolicy(RetentionMode::LegalHold, 0, 3650, '§ 147 AO');
    expect($hold->requiresLegalHold())->toBeTrue();
});

it('roundtrips via snapshot', function () {
    $policy = new RetentionPolicy(
        mode: RetentionMode::LegalHold,
        gracePeriodDays: 14,
        legalHoldDays: 3650,
        legalBasis: '§ 147 AO',
    );

    $snapshot = $policy->toSnapshot();

    expect($snapshot)->toBe([
        'mode' => 'legal_hold',
        'grace_period_days' => 14,
        'legal_hold_days' => 3650,
        'legal_basis' => '§ 147 AO',
    ]);

    $restored = RetentionPolicy::fromSnapshot($snapshot);

    expect($restored)->toEqual($policy);
});
