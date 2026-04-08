<?php

declare(strict_types=1);

use GraystackIt\Gdpr\Contracts\PersonalData;
use GraystackIt\Gdpr\Enums\RetentionMode;
use GraystackIt\Gdpr\Support\ModelRegistry;
use GraystackIt\Gdpr\Support\PersonalDataBlueprint;

class RegistryTestModel implements PersonalData
{
    public function personalData(PersonalDataBlueprint $b): PersonalDataBlueprint
    {
        return $b
            ->field('email')->anonymizeWith('email')->exportable()
            ->retention(mode: RetentionMode::Delete, gracePeriodDays: 7)
            ->processOrder(1000);
    }
}

it('lists registered models (simple and with config)', function () {
    $registry = new ModelRegistry([
        RegistryTestModel::class,
        'Vendor\\Pkg\\Thing' => ['profile' => 'Some\\Profile', 'scope' => 'Some\\Scope'],
    ]);

    expect($registry->all())
        ->toContain(RegistryTestModel::class)
        ->toContain('Vendor\\Pkg\\Thing');

    expect($registry->has(RegistryTestModel::class))->toBeTrue()
        ->and($registry->has('Unknown'))->toBeFalse();
});

it('builds and caches a blueprint for a self-describing model', function () {
    $registry = new ModelRegistry([RegistryTestModel::class]);

    $blueprint = $registry->blueprintFor(RegistryTestModel::class);

    expect($blueprint)->toBeInstanceOf(PersonalDataBlueprint::class)
        ->and($blueprint->isFrozen())->toBeTrue()
        ->and($blueprint->getProcessOrder())->toBe(1000);

    // Cached: second call returns the same instance
    expect($registry->blueprintFor(RegistryTestModel::class))->toBe($blueprint);
});

it('resolves scope class from config', function () {
    $registry = new ModelRegistry([
        'Vendor\\Pkg\\Thing' => ['profile' => 'App\\Profile', 'scope' => 'App\\Scope'],
    ]);

    expect($registry->scopeClassFor('Vendor\\Pkg\\Thing'))->toBe('App\\Scope')
        ->and($registry->scopeClassFor(RegistryTestModel::class))->toBeNull();
});

it('throws when a model has neither profile nor PersonalData contract', function () {
    $registry = new ModelRegistry(['stdClass']);

    $registry->blueprintFor('stdClass');
})->throws(InvalidArgumentException::class, 'not registered with a profile');
