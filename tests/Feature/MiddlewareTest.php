<?php

declare(strict_types=1);

use GraystackIt\Gdpr\Enums\ConsentPurpose;
use GraystackIt\Gdpr\Middleware\ApplyConsentCookies;
use GraystackIt\Gdpr\Middleware\RequireConsent;
use GraystackIt\Gdpr\Middleware\RequireNoDeletionPending;
use GraystackIt\Gdpr\Support\ConsentManager;
use GraystackIt\Gdpr\Support\DeletionScheduler;
use Illuminate\Support\Facades\Route;
use Workbench\App\Models\Address;
use Workbench\App\Models\User;

beforeEach(function () {
    Route::middleware(RequireConsent::class.':marketing')
        ->get('/marketing', fn () => response()->json(['ok' => true]));

    Route::middleware(RequireConsent::class.':necessary')
        ->get('/necessary', fn () => response()->json(['ok' => true]));

    Route::middleware(ApplyConsentCookies::class)
        ->get('/with-cookies', fn () => response()->json(['ok' => true]));

    Route::middleware(RequireNoDeletionPending::class)
        ->get('/auth', fn () => response()->json(['ok' => true]));
});

it('RequireConsent returns 451 when purpose not granted', function () {
    $response = $this->getJson('/marketing');

    $response->assertStatus(451);
    $response->assertJsonPath('purpose', 'marketing');
});

it('RequireConsent passes through when authenticated user granted purpose', function () {
    $user = User::create(['name' => 'Ada', 'email' => 'ada@example.com']);

    app(ConsentManager::class)
        ->grant($user, ConsentPurpose::Marketing);

    $response = $this->actingAs($user)->getJson('/marketing');

    $response->assertStatus(200);
});

it('RequireConsent always passes through Necessary', function () {
    $response = $this->getJson('/necessary');

    $response->assertStatus(200);
});

it('ApplyConsentCookies re-attaches cookie to response when present', function () {
    // Simulate a request carrying the cookie directly via the server bag.
    $payload = json_encode(['marketing' => true, 'policy_version' => '2026-04']);

    $response = $this->call('GET', '/with-cookies', cookies: ['gdpr_consent' => $payload]);

    $response->assertStatus(200);
    $cookies = $response->headers->getCookies();
    $names = array_map(fn ($c) => $c->getName(), $cookies);
    expect($names)->toContain('gdpr_consent');
});

it('RequireNoDeletionPending blocks authenticated users with pending deletion', function () {
    $user = User::create(['name' => 'Ada', 'email' => 'ada@example.com']);
    Address::create(['user_id' => $user->id, 'line1' => 'Main 1']);

    app(DeletionScheduler::class)->requestDeletion($user);

    $response = $this->actingAs($user)->getJson('/auth');

    $response->assertStatus(423);
});

it('RequireNoDeletionPending allows users without pending deletion', function () {
    $user = User::create(['name' => 'Bob', 'email' => 'bob@example.com']);

    $response = $this->actingAs($user)->getJson('/auth');

    $response->assertStatus(200);
});
