<?php

declare(strict_types=1);

use GraystackIt\Gdpr\Enums\ConsentPurpose;
use GraystackIt\Gdpr\Enums\DeletionState;
use GraystackIt\Gdpr\Models\GdprAudit;
use GraystackIt\Gdpr\Models\GdprDeletion;
use GraystackIt\Gdpr\Models\GdprRequest;
use Illuminate\Support\Facades\Schema;
use Workbench\App\Models\Applicant;
use Workbench\App\Models\ApplicantNote;
use Workbench\App\Models\User;

function seedApplicant(): Applicant
{
    $applicant = Applicant::create(['name' => 'Grace Hopper', 'email' => 'grace@example.com']);
    ApplicantNote::create(['applicant_id' => $applicant->id, 'body' => 'Strong candidate.']);

    return $applicant;
}

it('creates string subject_id columns', function (string $table) {
    expect(Schema::getColumnType($table, 'subject_id'))->toBe('varchar');
})->with(['gdpr_consents', 'gdpr_requests', 'gdpr_deletions', 'gdpr_audits', 'gdpr_policy_acceptances']);

it('stores consent for a uuid subject', function () {
    $applicant = seedApplicant();

    $applicant->grantConsent(ConsentPurpose::TalentPool, 'application_form');

    expect($applicant->hasConsent(ConsentPurpose::TalentPool))->toBeTrue()
        ->and($applicant->consents()->first()->subject_id)->toBe($applicant->id);

    $applicant->withdrawConsent(ConsentPurpose::TalentPool);

    expect($applicant->hasConsent(ConsentPurpose::TalentPool))->toBeFalse();
});

it('schedules a deletion for a uuid subject', function () {
    $applicant = seedApplicant();

    $request = $applicant->requestDeletion();

    expect($request->subject_id)->toBe($applicant->id)
        ->and($applicant->isDeletionPending())->toBeTrue();

    $deletions = GdprDeletion::where('gdpr_request_id', $request->id)->orderBy('process_order')->get();

    expect($deletions)->toHaveCount(2)
        ->and($deletions->pluck('target_model')->all())->toBe([ApplicantNote::class, Applicant::class])
        ->and($deletions->pluck('subject_id')->unique()->all())->toBe([$applicant->id]);
});

it('erases a uuid subject and its related rows', function () {
    $applicant = seedApplicant();

    $applicant->deleteImmediately();

    expect(Applicant::find($applicant->id))->toBeNull()
        ->and(ApplicantNote::where('applicant_id', $applicant->id)->first()->body)->not->toBe('Strong candidate.');

    $states = GdprDeletion::where('subject_id', $applicant->id)->orderBy('process_order')->pluck('state')->all();

    expect($states)->toBe([DeletionState::Anonymized, DeletionState::Erased]);
});

it('writes audit rows carrying the uuid subject id', function () {
    $applicant = seedApplicant();

    $applicant->requestDeletion();

    expect(GdprAudit::where('subject_id', $applicant->id)->where('event', 'deletion_requested')->exists())
        ->toBeTrue();
});

it('keeps numeric subjects working alongside uuid subjects', function () {
    $applicant = seedApplicant();
    $user = User::create(['name' => 'Ada Lovelace', 'email' => 'ada@example.com']);

    $applicant->requestDeletion();
    $user->requestDeletion();

    expect(GdprRequest::where('subject_type', User::class)->where('subject_id', (string) $user->id)->exists())->toBeTrue()
        ->and($user->isDeletionPending())->toBeTrue()
        ->and($applicant->isDeletionPending())->toBeTrue();
});

it('scopes pending-deletion queries by uuid subject', function () {
    $pending = seedApplicant();
    $untouched = Applicant::create(['name' => 'Alan Turing', 'email' => 'alan@example.com']);

    $pending->requestDeletion();

    expect(Applicant::whereDeletionPending()->pluck('id')->all())->toBe([$pending->id])
        ->and(Applicant::whereNotDeletionPending()->pluck('id')->all())->toBe([$untouched->id]);
});
