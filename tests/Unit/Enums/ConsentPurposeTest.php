<?php

declare(strict_types=1);

use GraystackIt\Gdpr\Enums\ConsentPurpose;

it('returns a human label for each case', function () {
    expect(ConsentPurpose::Necessary->label())->toBe('Strictly necessary')
        ->and(ConsentPurpose::Analytics->label())->toBe('Analytics')
        ->and(ConsentPurpose::Marketing->label())->toBe('Marketing');
});

it('only necessary purpose bypasses consent', function () {
    expect(ConsentPurpose::Necessary->requiresConsent())->toBeFalse()
        ->and(ConsentPurpose::Necessary->isOptional())->toBeFalse();

    foreach ([ConsentPurpose::Analytics, ConsentPurpose::Marketing, ConsentPurpose::EmbeddedContent, ConsentPurpose::Personalization] as $purpose) {
        expect($purpose->requiresConsent())->toBeTrue()
            ->and($purpose->isOptional())->toBeTrue();
    }
});

it('coerces enum, string and null via fromMixed', function () {
    expect(ConsentPurpose::fromMixed(ConsentPurpose::Analytics))->toBe(ConsentPurpose::Analytics)
        ->and(ConsentPurpose::fromMixed('marketing'))->toBe(ConsentPurpose::Marketing)
        ->and(ConsentPurpose::fromMixed('unknown'))->toBeNull()
        ->and(ConsentPurpose::fromMixed(null))->toBeNull()
        ->and(ConsentPurpose::fromMixed(42))->toBeNull();
});
