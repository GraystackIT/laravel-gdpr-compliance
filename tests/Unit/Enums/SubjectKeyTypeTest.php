<?php

declare(strict_types=1);

use GraystackIt\Gdpr\Enums\SubjectKeyType;
use Workbench\App\Models\User;

it('defaults to bigint', function () {
    expect(SubjectKeyType::configured())->toBe(SubjectKeyType::BigInteger);
});

it('rejects an unknown configured value', function () {
    config()->set('gdpr.subject_key_type', 'integer');

    SubjectKeyType::configured();
})->throws(InvalidArgumentException::class, 'Invalid config("gdpr.subject_key_type") [integer]');

it('leaves keys untouched for bigint and stringifies them otherwise', function () {
    expect(SubjectKeyType::BigInteger->cast(42))->toBe(42)
        ->and(SubjectKeyType::Uuid->cast(42))->toBe('42')
        ->and(SubjectKeyType::Ulid->cast(42))->toBe('42')
        ->and(SubjectKeyType::String->cast(42))->toBe('42');
});

it('casts a subject key to the configured type', function () {
    $user = new User(['name' => 'Ada']);
    $user->id = 7;

    expect(SubjectKeyType::of($user))->toBe(7);

    config()->set('gdpr.subject_key_type', 'string');

    expect(SubjectKeyType::of($user))->toBe('7');
});
