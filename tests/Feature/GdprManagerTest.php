<?php

declare(strict_types=1);

use GraystackIt\Gdpr\Enums\ConsentPurpose;
use GraystackIt\Gdpr\Enums\RequestStatus;
use GraystackIt\Gdpr\Enums\RequestType;
use GraystackIt\Gdpr\Models\GdprRequest;
use GraystackIt\Gdpr\Support\GdprManager;
use Workbench\App\Models\Address;
use Workbench\App\Models\Order;
use Workbench\App\Models\User;

beforeEach(function () {
    $this->manager = app(GdprManager::class);
});

it('creates a request via trait method requestDeletion', function () {
    $user = User::create(['name' => 'Ada', 'email' => 'ada@example.com']);
    Order::create(['user_id' => $user->id, 'billing_email' => 'ada@example.com', 'total' => 10]);

    $request = $user->requestDeletion();

    expect($request)->toBeInstanceOf(GdprRequest::class)
        ->and($request->type)->toBe(RequestType::Delete);
});

it('isDeletionPending returns true after request and false after cancellation', function () {
    $user = User::create(['name' => 'Ada', 'email' => 'ada@example.com']);
    Address::create(['user_id' => $user->id, 'line1' => 'Main 1', 'city' => 'Vienna']);

    expect($user->isDeletionPending())->toBeFalse();

    $user->requestDeletion();
    expect($user->isDeletionPending())->toBeTrue();

    $user->cancelDeletion();
    expect($user->fresh()->isDeletionPending())->toBeFalse();
});

it('deleteImmediately processes all rows in one call', function () {
    $user = User::create(['name' => 'Ada', 'email' => 'ada@example.com']);
    Address::create(['user_id' => $user->id, 'line1' => 'Main 1', 'city' => 'Vienna']);

    $this->manager->deleteImmediately($user);

    expect(User::find($user->id))->toBeNull()
        ->and(Address::where('user_id', $user->id)->count())->toBe(0);
});

it('whereDeletionPending scope filters subjects correctly', function () {
    $alice = User::create(['name' => 'Alice', 'email' => 'alice@example.com']);
    $bob = User::create(['name' => 'Bob', 'email' => 'bob@example.com']);

    // Both need at least one affected row for the scheduler to create a gdpr_deletions entry
    Address::create(['user_id' => $alice->id, 'line1' => 'A']);
    Address::create(['user_id' => $bob->id, 'line1' => 'B']);

    $alice->requestDeletion();

    $pending = User::whereDeletionPending()->get();
    $notPending = User::whereNotDeletionPending()->get();

    expect($pending->pluck('id')->all())->toBe([$alice->id])
        ->and($notPending->pluck('id')->all())->toBe([$bob->id]);
});

it('requestExport creates an export request with email snapshot', function () {
    $user = User::create(['name' => 'Ada', 'email' => 'ada@example.com']);

    $request = $user->requestExport();

    expect($request->type)->toBe(RequestType::Export)
        ->and($request->status)->toBe(RequestStatus::Pending)
        ->and($request->notification_email)->toBe('ada@example.com');
});

it('consent trait proxies to ConsentManager', function () {
    $user = User::create(['name' => 'Ada', 'email' => 'ada@example.com']);

    $user->grantConsent(ConsentPurpose::Analytics);

    expect($user->hasConsent(ConsentPurpose::Analytics))->toBeTrue()
        ->and($user->consents)->toHaveCount(1);

    $user->withdrawConsent(ConsentPurpose::Analytics);

    expect($user->hasConsent(ConsentPurpose::Analytics))->toBeFalse();
});
