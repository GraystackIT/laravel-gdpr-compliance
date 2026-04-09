<?php

declare(strict_types=1);

use GraystackIt\Gdpr\Anonymizers\EmailAnonymizer;
use GraystackIt\Gdpr\Anonymizers\NameAnonymizer;
use GraystackIt\Gdpr\Support\AnonymizerManager;

it('resolves registered anonymizers by alias', function () {
    $m = new AnonymizerManager([
        'name' => NameAnonymizer::class,
        'email' => EmailAnonymizer::class,
    ]);

    expect($m->has('name'))->toBeTrue()
        ->and($m->has('unknown'))->toBeFalse()
        ->and($m->resolve('name'))->toBeInstanceOf(NameAnonymizer::class);
});

it('caches resolved instances', function () {
    $m = new AnonymizerManager(['name' => NameAnonymizer::class]);

    $first = $m->resolve('name');
    $second = $m->resolve('name');

    expect($first)->toBe($second);
});

it('anonymizes via alias in one call', function () {
    $m = new AnonymizerManager(['name' => NameAnonymizer::class]);

    expect($m->anonymize('name', 'John Doe'))->toBe('Anonymous User');
});

it('throws when alias is unknown', function () {
    (new AnonymizerManager)->resolve('missing');
})->throws(InvalidArgumentException::class, 'Unknown anonymizer alias');

it('registers a new alias at runtime', function () {
    $m = new AnonymizerManager;
    $m->register('name', NameAnonymizer::class);

    expect($m->has('name'))->toBeTrue();
});
