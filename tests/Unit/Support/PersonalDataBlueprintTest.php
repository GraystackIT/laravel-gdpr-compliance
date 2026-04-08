<?php

declare(strict_types=1);

use GraystackIt\Gdpr\Enums\RetentionMode;
use GraystackIt\Gdpr\Support\InvalidRetentionConfig;
use GraystackIt\Gdpr\Support\PersonalDataBlueprint;

it('chains field declarations with anonymizer and exportable', function () {
    $b = (new PersonalDataBlueprint)
        ->field('name')->anonymizeWith('name')->exportable()
        ->field('email')->anonymizeWith('email', ['mask' => 'partial'])->exportable()
        ->field('password')->anonymizeWith('static_text', ['value' => '[X]'])
        ->field('created_at')->exportable()
        ->retention(mode: RetentionMode::Delete, gracePeriodDays: 7);

    $b->build();

    expect($b->fields())->toHaveCount(4)
        ->and($b->anonymizableFields())->toHaveCount(3)
        ->and($b->exportableFields())->toHaveCount(3);

    $fields = collect($b->fields())->keyBy('name');
    expect($fields['email']->anonymizerConfig)->toBe(['mask' => 'partial'])
        ->and($fields['created_at']->isAnonymizable())->toBeFalse()
        ->and($fields['created_at']->isExportable())->toBeTrue()
        ->and($fields['password']->isAnonymizable())->toBeTrue()
        ->and($fields['password']->isExportable())->toBeFalse();
});

it('throws when a field has neither anonymizer nor exportable', function () {
    $b = (new PersonalDataBlueprint)
        ->field('ghost');

    $b->build();
})->throws(InvalidRetentionConfig::class, 'field "ghost"');

it('throws when grace period exceeds 30 days', function () {
    (new PersonalDataBlueprint)
        ->field('name')->anonymizeWith('name')
        ->retention(mode: RetentionMode::Delete, gracePeriodDays: 31);
})->throws(InvalidRetentionConfig::class, 'may not exceed 30');

it('throws when grace period is negative', function () {
    (new PersonalDataBlueprint)
        ->field('name')->anonymizeWith('name')
        ->retention(mode: RetentionMode::Delete, gracePeriodDays: -1);
})->throws(InvalidRetentionConfig::class, 'must be >= 0');

it('throws when legal_hold mode has no legalHoldDays', function () {
    (new PersonalDataBlueprint)
        ->field('name')->anonymizeWith('name')
        ->retention(mode: RetentionMode::LegalHold, gracePeriodDays: 0);
})->throws(InvalidRetentionConfig::class, 'legalHoldDays must be > 0');

it('uses default retention when not configured', function () {
    $b = (new PersonalDataBlueprint)
        ->field('name')->anonymizeWith('name');

    $policy = $b->retentionPolicy();

    expect($policy->mode)->toBe(RetentionMode::Delete)
        ->and($policy->gracePeriodDays)->toBe(0)
        ->and($policy->legalHoldDays)->toBeNull();
});

it('defaults processOrder to 100', function () {
    $b = (new PersonalDataBlueprint)
        ->field('name')->anonymizeWith('name');

    expect($b->getProcessOrder())->toBe(PersonalDataBlueprint::DEFAULT_PROCESS_ORDER);
});

it('respects custom processOrder', function () {
    $b = (new PersonalDataBlueprint)
        ->field('name')->anonymizeWith('name')
        ->processOrder(1000);

    expect($b->getProcessOrder())->toBe(1000);
});

it('validates processOrder bounds', function () {
    (new PersonalDataBlueprint)
        ->field('name')->anonymizeWith('name')
        ->processOrder(99999);
})->throws(InvalidRetentionConfig::class, 'between 0 and 65535');

it('freezes after build and rejects further mutation', function () {
    $b = (new PersonalDataBlueprint)
        ->field('name')->anonymizeWith('name');

    $b->build();

    expect($b->isFrozen())->toBeTrue();

    $b->field('extra');
})->throws(LogicException::class, 'frozen');

it('requires a current field before anonymizeWith or exportable', function () {
    (new PersonalDataBlueprint)->anonymizeWith('name');
})->throws(LogicException::class, 'no current field');
