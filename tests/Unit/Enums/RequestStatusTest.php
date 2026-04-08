<?php

declare(strict_types=1);

use GraystackIt\Gdpr\Enums\RequestStatus;

it('identifies terminal statuses', function () {
    expect(RequestStatus::Completed->isTerminal())->toBeTrue()
        ->and(RequestStatus::Cancelled->isTerminal())->toBeTrue()
        ->and(RequestStatus::Failed->isTerminal())->toBeTrue()
        ->and(RequestStatus::Pending->isTerminal())->toBeFalse()
        ->and(RequestStatus::Processing->isTerminal())->toBeFalse();
});
