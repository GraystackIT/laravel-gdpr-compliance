<?php

declare(strict_types=1);

use GraystackIt\Gdpr\Models\GdprPolicyVersion;
use GraystackIt\Gdpr\Support\PolicyLinkManager;
use Workbench\App\Models\User;

it('resolves url from config entry', function () {
    $m = new PolicyLinkManager([
        'privacy' => ['url' => 'https://example.com/privacy'],
    ]);

    expect($m->urlFor('privacy'))->toBe('https://example.com/privacy')
        ->and($m->urlFor('imprint'))->toBeNull();
});

it('returns null when entry has neither url nor valid route', function () {
    $m = new PolicyLinkManager([
        'privacy' => ['route' => 'nonexistent.route'],
    ]);

    expect($m->urlFor('privacy'))->toBeNull();
});

it('records an acceptance for a subject', function () {
    $user = User::create(['name' => 'Ada']);
    $version = GdprPolicyVersion::create([
        'slug' => 'privacy',
        'version' => '2026-04',
        'published_at' => now(),
    ]);

    $m = new PolicyLinkManager;
    $m->recordAcceptance($user, $version, ['source' => 'login']);

    expect($m->hasAccepted($user, 'privacy'))->toBeTrue()
        ->and($m->hasAccepted($user, 'imprint'))->toBeFalse();
});

it('returns latest version by published_at', function () {
    GdprPolicyVersion::create(['slug' => 'privacy', 'version' => '2025-01', 'published_at' => now()->subYear()]);
    GdprPolicyVersion::create(['slug' => 'privacy', 'version' => '2026-04', 'published_at' => now()]);

    $m = new PolicyLinkManager;
    expect($m->latestVersion('privacy')->version)->toBe('2026-04');
});
