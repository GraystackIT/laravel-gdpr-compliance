<?php

declare(strict_types=1);

use GraystackIt\Gdpr\Enums\DeletionState;

it('identifies terminal states', function () {
    expect(DeletionState::Anonymized->isTerminal())->toBeTrue()
        ->and(DeletionState::Erased->isTerminal())->toBeTrue()
        ->and(DeletionState::Cancelled->isTerminal())->toBeTrue()
        ->and(DeletionState::PendingGrace->isTerminal())->toBeFalse()
        ->and(DeletionState::PendingLegalHold->isTerminal())->toBeFalse();
});

it('allows transitions from pending_grace to any resolution', function () {
    $from = DeletionState::PendingGrace;

    expect($from->canTransitionTo(DeletionState::Cancelled))->toBeTrue()
        ->and($from->canTransitionTo(DeletionState::Erased))->toBeTrue()
        ->and($from->canTransitionTo(DeletionState::Anonymized))->toBeTrue()
        ->and($from->canTransitionTo(DeletionState::PendingLegalHold))->toBeTrue();
});

it('only allows pending_legal_hold to transition to erased', function () {
    $from = DeletionState::PendingLegalHold;

    expect($from->canTransitionTo(DeletionState::Erased))->toBeTrue()
        ->and($from->canTransitionTo(DeletionState::Anonymized))->toBeFalse()
        ->and($from->canTransitionTo(DeletionState::Cancelled))->toBeFalse()
        ->and($from->canTransitionTo(DeletionState::PendingGrace))->toBeFalse();
});

it('forbids transitions from terminal states', function () {
    foreach ([DeletionState::Anonymized, DeletionState::Erased, DeletionState::Cancelled] as $from) {
        foreach (DeletionState::cases() as $to) {
            expect($from->canTransitionTo($to))->toBeFalse();
        }
    }
});
