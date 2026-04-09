<?php

declare(strict_types=1);

use GraystackIt\Gdpr\Enums\DeletionState;
use GraystackIt\Gdpr\Enums\RequestStatus;
use GraystackIt\Gdpr\Enums\RequestType;
use GraystackIt\Gdpr\Events\PersonalDataExported;
use GraystackIt\Gdpr\Jobs\PreparePersonalDataExportJob;
use GraystackIt\Gdpr\Jobs\ProcessSubjectDeletionJob;
use GraystackIt\Gdpr\Models\GdprDeletion;
use GraystackIt\Gdpr\Models\GdprRequest;
use GraystackIt\Gdpr\Support\DeletionScheduler;
use GraystackIt\Gdpr\Support\PersonalDataExporter;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Workbench\App\Models\Address;
use Workbench\App\Models\User;

it('PreparePersonalDataExportJob writes file and marks request completed', function () {
    Storage::fake('local');
    Event::fake([PersonalDataExported::class]);

    $user = User::create(['name' => 'Ada', 'email' => 'ada@example.com']);

    $request = GdprRequest::create([
        'subject_type' => User::class,
        'subject_id' => $user->id,
        'type' => RequestType::Export,
        'status' => RequestStatus::Pending,
        'requested_at' => now(),
    ]);

    (new PreparePersonalDataExportJob($request->id))->handle(
        app(PersonalDataExporter::class)
    );

    expect($request->fresh()->status)->toBe(RequestStatus::Completed)
        ->and($request->fresh()->export_file_path)->not->toBeNull();

    Storage::disk('local')->assertExists($request->fresh()->export_file_path);
    Event::assertDispatched(PersonalDataExported::class);
});

it('PreparePersonalDataExportJob is idempotent on terminal requests', function () {
    Storage::fake('local');

    $user = User::create(['name' => 'Ada', 'email' => 'ada@example.com']);

    $request = GdprRequest::create([
        'subject_type' => User::class,
        'subject_id' => $user->id,
        'type' => RequestType::Export,
        'status' => RequestStatus::Completed,
        'requested_at' => now()->subHour(),
        'completed_at' => now()->subMinute(),
    ]);

    (new PreparePersonalDataExportJob($request->id))->handle(
        app(PersonalDataExporter::class)
    );

    // No new export file because request was already terminal
    expect($request->fresh()->export_file_path)->toBeNull();
});

it('ProcessSubjectDeletionJob processes a single deletion row', function () {
    $user = User::create(['name' => 'Ada', 'email' => 'ada@example.com']);
    Address::create(['user_id' => $user->id, 'line1' => 'Main 1']);

    $scheduler = app(DeletionScheduler::class);
    $request = $scheduler->requestDeletion($user);

    // Fast forward
    GdprDeletion::query()->update(['scheduled_for' => now()->subDay()]);

    $addressDeletion = GdprDeletion::where('target_model', Address::class)->first();

    (new ProcessSubjectDeletionJob($addressDeletion->id))->handle($scheduler);

    expect($addressDeletion->fresh()->state)->toBe(DeletionState::Erased)
        ->and(Address::where('user_id', $user->id)->count())->toBe(0);
});
