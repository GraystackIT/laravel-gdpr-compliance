<?php

declare(strict_types=1);

use GraystackIt\Gdpr\Enums\DeletionState;
use GraystackIt\Gdpr\Enums\RequestStatus;
use GraystackIt\Gdpr\Events\LegalHoldExpired;
use GraystackIt\Gdpr\Events\LegalHoldStarted;
use GraystackIt\Gdpr\Events\PersonalDataAnonymized;
use GraystackIt\Gdpr\Events\PersonalDataDeletionCancelled;
use GraystackIt\Gdpr\Events\PersonalDataDeletionRequested;
use GraystackIt\Gdpr\Events\PersonalDataErased;
use GraystackIt\Gdpr\Models\GdprAudit;
use GraystackIt\Gdpr\Models\GdprDeletion;
use GraystackIt\Gdpr\Models\GdprRequest;
use GraystackIt\Gdpr\Support\DeletionScheduler;
use Illuminate\Support\Facades\Event;
use Workbench\App\Models\Address;
use Workbench\App\Models\Order;
use Workbench\App\Models\User;

beforeEach(function () {
    Event::fake([
        PersonalDataDeletionRequested::class,
        PersonalDataDeletionCancelled::class,
        PersonalDataAnonymized::class,
        PersonalDataErased::class,
        LegalHoldStarted::class,
        LegalHoldExpired::class,
    ]);

    $this->scheduler = app(DeletionScheduler::class);
});

function seedSubjectWithData(): User
{
    $user = User::create([
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'phone' => '+43664000000',
        'password' => 'secret',
    ]);
    Order::create(['user_id' => $user->id, 'billing_email' => 'ada@example.com', 'shipping_address' => 'Main St 1', 'total' => 29.90]);
    Order::create(['user_id' => $user->id, 'billing_email' => 'ada@example.com', 'shipping_address' => 'Main St 1', 'total' => 49.00]);
    Address::create(['user_id' => $user->id, 'line1' => 'Main St 1', 'city' => 'Vienna']);

    return $user;
}

it('creates one gdpr_deletions row per affected registered model on requestDeletion', function () {
    $user = seedSubjectWithData();

    $request = $this->scheduler->requestDeletion($user);

    expect($request)->toBeInstanceOf(GdprRequest::class)
        ->and($request->status)->toBe(RequestStatus::Pending)
        ->and($request->notification_email)->toBe('ada@example.com');

    $deletions = GdprDeletion::where('gdpr_request_id', $request->id)->orderBy('process_order')->get();

    expect($deletions)->toHaveCount(3); // Order, Address, User

    expect($deletions->pluck('target_model')->all())
        ->toBe([Order::class, Address::class, User::class]); // sorted by process_order 100, 200, 1000

    foreach ($deletions as $d) {
        expect($d->state)->toBe(DeletionState::PendingGrace);
    }
});

it('does NOT touch host rows during grace', function () {
    $user = seedSubjectWithData();

    $this->scheduler->requestDeletion($user);

    expect(User::find($user->id)->name)->toBe('Ada Lovelace')
        ->and(Order::where('user_id', $user->id)->count())->toBe(2)
        ->and(Address::where('user_id', $user->id)->count())->toBe(1);
});

it('writes per-model deletion_scheduled audits plus deletion_requested', function () {
    $user = seedSubjectWithData();

    $this->scheduler->requestDeletion($user);

    expect(GdprAudit::where('event', 'deletion_requested')->count())->toBe(1)
        ->and(GdprAudit::where('event', 'deletion_scheduled')->count())->toBe(3);

    $orderAudit = GdprAudit::where('event', 'deletion_scheduled')->where('target_model', Order::class)->first();
    expect($orderAudit->affected_rows)->toBe(2)
        ->and($orderAudit->context['retention_snapshot']['mode'])->toBe('legal_hold');
});

it('fires PersonalDataDeletionRequested event', function () {
    $user = seedSubjectWithData();

    $this->scheduler->requestDeletion($user);

    Event::assertDispatched(PersonalDataDeletionRequested::class);
});

it('cancellation marks all pending_grace rows cancelled and leaves host rows intact', function () {
    $user = seedSubjectWithData();
    $request = $this->scheduler->requestDeletion($user);

    $this->scheduler->cancelDeletion($request);

    expect($request->fresh()->status)->toBe(RequestStatus::Cancelled)
        ->and(GdprDeletion::where('state', DeletionState::Cancelled)->count())->toBe(3)
        ->and(User::find($user->id))->not->toBeNull()
        ->and(Order::where('user_id', $user->id)->count())->toBe(2);

    Event::assertDispatched(PersonalDataDeletionCancelled::class);
});

it('processes due deletions in process_order sequence per mode', function () {
    $user = seedSubjectWithData();
    $request = $this->scheduler->requestDeletion($user);

    // Fast-forward past grace
    GdprDeletion::query()->update(['scheduled_for' => now()->subDay()]);

    $result = $this->scheduler->processDueDeletions();

    expect($result['pass1'])->toBe(3);

    // Order: anonymized + pending_legal_hold
    $orderRow = GdprDeletion::where('target_model', Order::class)->first();
    expect($orderRow->state)->toBe(DeletionState::PendingLegalHold)
        ->and($orderRow->hold_until)->not->toBeNull();
    // Order host rows: PII wiped, rows still present
    $orders = Order::where('user_id', $user->id)->get();
    expect($orders)->toHaveCount(2);
    foreach ($orders as $o) {
        expect($o->billing_email)->not->toBe('ada@example.com');
    }

    // Address: mode=delete → rows gone
    expect(Address::where('user_id', $user->id)->count())->toBe(0);

    // User: mode=delete → row gone
    expect(User::find($user->id))->toBeNull();

    // Request is NOT yet completed because Order is still in legal hold
    expect($request->fresh()->status)->toBe(RequestStatus::Pending);

    Event::assertDispatched(PersonalDataAnonymized::class);
    Event::assertDispatched(LegalHoldStarted::class);
    Event::assertDispatched(PersonalDataErased::class);
});

it('pass 2 force-deletes legal hold rows and completes the request', function () {
    $user = seedSubjectWithData();
    $request = $this->scheduler->requestDeletion($user);

    GdprDeletion::query()->update(['scheduled_for' => now()->subDay()]);
    $this->scheduler->processDueDeletions();

    // Expire the legal hold
    GdprDeletion::where('state', DeletionState::PendingLegalHold)
        ->update(['hold_until' => now()->subDay()]);

    $result = $this->scheduler->processDueDeletions();

    expect($result['pass2'])->toBe(1)
        ->and(Order::where('user_id', $user->id)->count())->toBe(0)
        ->and($request->fresh()->status)->toBe(RequestStatus::Completed);

    Event::assertDispatched(LegalHoldExpired::class);
});

it('snapshot wins over live profile changes', function () {
    $user = seedSubjectWithData();
    $request = $this->scheduler->requestDeletion($user);

    // Manually mutate the snapshot on the Order gdpr_deletions row
    // to simulate a profile that was different at request time.
    $orderRow = GdprDeletion::where('target_model', Order::class)->first();
    $snapshot = $orderRow->retention_snapshot;
    $snapshot['mode'] = 'delete';
    $orderRow->retention_snapshot = $snapshot;
    $orderRow->save();

    // Fast forward
    GdprDeletion::query()->update(['scheduled_for' => now()->subDay()]);
    $this->scheduler->processDueDeletions();

    // Orders should be force-deleted because snapshot says 'delete'
    expect(Order::where('user_id', $user->id)->count())->toBe(0);
});
