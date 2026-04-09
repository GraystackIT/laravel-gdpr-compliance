<?php

declare(strict_types=1);

use GraystackIt\Gdpr\Enums\ConsentPurpose;
use GraystackIt\Gdpr\Support\ConsentCookieManager;
use Illuminate\Http\Request;

it('reads null when no cookie is present', function () {
    $req = Request::create('/');
    $m = new ConsentCookieManager;

    expect($m->read($req))->toBeNull();
});

it('parses a json cookie', function () {
    $payload = json_encode([
        'necessary' => true,
        'analytics' => false,
        'marketing' => true,
        'policy_version' => '2026-04',
    ]);
    $req = Request::create('/', cookies: ['gdpr_consent' => $payload]);

    $m = new ConsentCookieManager;
    expect($m->read($req))
        ->toBe([
            'necessary' => true,
            'analytics' => false,
            'marketing' => true,
            'policy_version' => '2026-04',
        ]);
});

it('has() returns true for Necessary regardless of cookie', function () {
    $req = Request::create('/');
    $m = new ConsentCookieManager;

    expect($m->has($req, ConsentPurpose::Necessary))->toBeTrue();
});

it('has() respects per-purpose flags in cookie', function () {
    $payload = json_encode(['analytics' => true, 'marketing' => false]);
    $req = Request::create('/', cookies: ['gdpr_consent' => $payload]);

    $m = new ConsentCookieManager;
    expect($m->has($req, ConsentPurpose::Analytics))->toBeTrue()
        ->and($m->has($req, ConsentPurpose::Marketing))->toBeFalse();
});

it('builds a cookie with policy version and defaults', function () {
    $m = new ConsentCookieManager;
    $cookie = $m->build([
        'analytics' => true,
        'marketing' => false,
    ], policyVersion: '2026-04');

    expect($cookie->getName())->toBe('gdpr_consent');

    $payload = json_decode($cookie->getValue(), true);
    expect($payload['necessary'])->toBeTrue()
        ->and($payload['analytics'])->toBeTrue()
        ->and($payload['marketing'])->toBeFalse()
        ->and($payload['policy_version'])->toBe('2026-04')
        ->and($payload)->toHaveKey('updated_at');
});
