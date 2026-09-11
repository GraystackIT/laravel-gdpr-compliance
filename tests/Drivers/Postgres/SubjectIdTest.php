<?php

declare(strict_types=1);

use GraystackIt\Gdpr\Tests\Support\SubjectIdScenarios;

it('matches pending deletions for every subject key type', function (string $keyType) {
    SubjectIdScenarios::assertDeletionPendingScopes($keyType);
})->with(['bigint', 'uuid', 'ulid', 'string']);

it('converts subject_id columns to the configured type and back', function (string $keyType) {
    SubjectIdScenarios::assertUpgradeMigrationRoundTrip($keyType);
})->with(['uuid', 'ulid', 'string']);

it('rolls a populated installation back to bigint', function () {
    SubjectIdScenarios::assertUpgradeMigrationKeepsStoredKeys();
});
