<?php

declare(strict_types=1);

use GraystackIt\Gdpr\Contracts\PersonalData;
use GraystackIt\Gdpr\Enums\RetentionMode;
use GraystackIt\Gdpr\Support\ModelRegistry;
use GraystackIt\Gdpr\Support\PersonalDataBlueprint;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

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

/**
 * A subject that is also a related model of another subject: its scope answers
 * for that other subject and "no rows" for anything else — including itself.
 */
class RegistrySubjectWithForeignScope extends Model
{
    protected $table = 'users';

    public function scopePersonalDataForSubject(Builder $query, Model $subject): Builder
    {
        return match (true) {
            $subject instanceof RegistryOtherSubject => $query->where('owner_id', $subject->getKey()),
            default => $query->whereRaw('1 = 0'),
        };
    }
}

class RegistrySubjectRemovingItsGlobalScope extends Model
{
    protected $table = 'users';

    protected static function booted(): void
    {
        static::addGlobalScope('tenant', function (Builder $query): void {
            $query->whereRaw('1 = 0');
        });
    }

    public function scopePersonalDataForSubject(Builder $query, Model $subject): Builder
    {
        return $query->withoutGlobalScope('tenant')->whereKey($subject->getKey());
    }
}

class RegistryOtherSubject extends Model
{
    protected $table = 'orders';
}

it('reaches the subject own row by key even when its scope answers "no rows" for itself', function () {
    $registry = new ModelRegistry([RegistrySubjectWithForeignScope::class]);

    $subject = new RegistrySubjectWithForeignScope;
    $subject->id = 7;

    $query = $registry->applyScopeFor($subject::class, $subject->newQuery(), $subject);

    expect($query->toSql())->not->toContain('1 = 0')
        ->and($query->getBindings())->toBe([7]);
});

it('takes the global scopes off the subject own row that its scope removes', function () {
    $registry = new ModelRegistry([RegistrySubjectRemovingItsGlobalScope::class]);

    $subject = new RegistrySubjectRemovingItsGlobalScope;
    $subject->id = 7;

    $query = $registry->applyScopeFor($subject::class, $subject->newQuery(), $subject);

    expect($query->toSql())->not->toContain('1 = 0')
        ->and($query->getBindings())->toBe([7]);
});

it('keeps scoping a model through its subject scope for every other subject', function () {
    $registry = new ModelRegistry([RegistrySubjectWithForeignScope::class]);

    $subject = new RegistryOtherSubject;
    $subject->id = 7;

    $query = $registry->applyScopeFor(
        RegistrySubjectWithForeignScope::class,
        (new RegistrySubjectWithForeignScope)->newQuery(),
        $subject,
    );

    expect($query->toSql())->toContain('"owner_id" = ?')
        ->and($query->getBindings())->toBe([7]);
});
