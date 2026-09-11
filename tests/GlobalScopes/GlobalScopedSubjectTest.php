<?php

declare(strict_types=1);

use GraystackIt\Gdpr\Enums\DeletionState;
use GraystackIt\Gdpr\Enums\RequestStatus;
use GraystackIt\Gdpr\Jobs\ProcessSubjectDeletionJob;
use GraystackIt\Gdpr\Models\GdprAudit;
use GraystackIt\Gdpr\Models\GdprDeletion;
use GraystackIt\Gdpr\Models\GdprRequest;
use GraystackIt\Gdpr\Support\DeletionScheduler;
use GraystackIt\Gdpr\Support\PersonalDataExporter;
use GraystackIt\Gdpr\Support\SubjectNotReachable;
use GraystackIt\Gdpr\Support\SubjectRecordResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Workbench\App\Models\Member;
use Workbench\App\Models\MemberNote;
use Workbench\App\Models\UnreachableMember;
use Workbench\App\Models\User;

/**
 * Bind (or unbind) the organization the global scope filters on. null is the
 * state a queue worker and the scheduler run in.
 */
function bindOrganization(?int $organizationId): void
{
    config()->set('workbench.organization_id', $organizationId);
}

function seedMember(): Member
{
    bindOrganization(1);

    $member = Member::create([
        'organization_id' => 1,
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
    ]);

    MemberNote::create([
        'organization_id' => 1,
        'member_id' => $member->id,
        'body' => 'Joined the association in 2019.',
    ]);

    return $member;
}

it('schedules a deletion row for the subject itself, not only for its related models', function () {
    $member = seedMember();

    $request = app(DeletionScheduler::class)->requestDeletion($member);

    expect(GdprDeletion::where('gdpr_request_id', $request->id)->orderBy('process_order')->pluck('target_model')->all())
        ->toBe([MemberNote::class, Member::class]);
});

it('erases a tenant-scoped subject when the scheduler runs without a bound organization', function () {
    $member = seedMember();

    $request = app(DeletionScheduler::class)->requestDeletion($member);
    GdprDeletion::query()->update(['scheduled_for' => now()->subDay()]);

    // The scheduler and the queue run outside any tenant context.
    bindOrganization(null);

    app(DeletionScheduler::class)->processDueDeletions();

    expect(DB::table('members')->where('id', $member->id)->exists())->toBeFalse()
        ->and(DB::table('member_notes')->where('member_id', $member->id)->value('body'))
        ->not->toBe('Joined the association in 2019.')
        ->and(GdprDeletion::where('gdpr_request_id', $request->id)->orderBy('process_order')->pluck('state')->all())
        ->toBe([DeletionState::Anonymized, DeletionState::Erased])
        ->and($request->fresh()->status)->toBe(RequestStatus::Completed);
});

it('resolves the subject row without a bound organization', function () {
    $member = seedMember();

    bindOrganization(null);

    $counts = app(SubjectRecordResolver::class)->countsFor($member);

    expect($counts[Member::class])->toBe(1)
        ->and($counts[MemberNote::class])->toBe(1);
});

it('exports the subject row without a bound organization', function () {
    Storage::fake('local');

    $member = seedMember();
    $request = $member->requestExport();

    bindOrganization(null);

    $result = app(PersonalDataExporter::class)->export($member, $request);
    $content = json_decode(Storage::disk('local')->get($result['path']), true, flags: JSON_THROW_ON_ERROR);

    expect($content['data'])->toHaveKey(Member::class)
        ->and($content['data'][Member::class][0]['email'])->toBe('ada@example.com');
});

it('refuses to schedule a deletion it cannot reach the subject through', function () {
    seedMember();

    $subject = UnreachableMember::first();

    bindOrganization(null);

    app(DeletionScheduler::class)->requestDeletion($subject);
})->throws(SubjectNotReachable::class, 'is not reachable through its registered scope');

it('leaves a deletion pending instead of reporting an erasure that never happened', function () {
    seedMember();

    $subject = UnreachableMember::first();
    $request = app(DeletionScheduler::class)->requestDeletion($subject);
    GdprDeletion::query()->update(['scheduled_for' => now()->subDay()]);

    bindOrganization(null);

    $result = app(DeletionScheduler::class)->processDueDeletions();

    expect($result['deferred'])->toBe(1)
        ->and(GdprDeletion::where('gdpr_request_id', $request->id)->pluck('state')->all())
        ->toBe([DeletionState::PendingGrace])
        ->and(DB::table('members')->count())->toBe(1)
        ->and($request->fresh()->status)->toBe(RequestStatus::Pending)
        ->and(GdprAudit::where('event', 'deletion_deferred')->where('target_model', UnreachableMember::class)->exists())
        ->toBeTrue();
});

it('reports unreachable subjects from the process-deletions command', function () {
    seedMember();

    app(DeletionScheduler::class)->requestDeletion(UnreachableMember::first());
    GdprDeletion::query()->update(['scheduled_for' => now()->subDay()]);

    bindOrganization(null);

    $this->artisan('gdpr:process-deletions')
        ->expectsOutputToContain('Left 1 row(s) pending')
        ->assertExitCode(1);
});

it('logs an error for every deletion it has to defer', function () {
    Log::spy();

    seedMember();

    app(DeletionScheduler::class)->requestDeletion(UnreachableMember::first());
    GdprDeletion::query()->update(['scheduled_for' => now()->subDay()]);

    bindOrganization(null);

    app(DeletionScheduler::class)->processDueDeletions();

    Log::shouldHaveReceived('error')
        ->once()
        ->withArgs(fn (string $message) => str_contains($message, 'is not reachable through its registered scope'));
});

it('fails the queued job for a subject it cannot reach', function () {
    seedMember();

    app(DeletionScheduler::class)->requestDeletion(UnreachableMember::first());

    bindOrganization(null);

    (new ProcessSubjectDeletionJob((int) GdprDeletion::first()->id))->handle(app(DeletionScheduler::class));
})->throws(SubjectNotReachable::class);

it('still marks a deletion erased when the subject row is really gone', function () {
    $member = seedMember();

    $request = app(DeletionScheduler::class)->requestDeletion($member);
    GdprDeletion::query()->update(['scheduled_for' => now()->subDay()]);

    // Deleted outside the package, e.g. by a cascading FK.
    DB::table('member_notes')->where('member_id', $member->id)->delete();
    DB::table('members')->where('id', $member->id)->delete();

    bindOrganization(null);

    app(DeletionScheduler::class)->processDueDeletions();

    expect(GdprDeletion::where('gdpr_request_id', $request->id)->pluck('state')->unique()->all())
        ->toBe([DeletionState::Erased])
        ->and($request->fresh()->status)->toBe(RequestStatus::Completed);
});

it('keeps subjects without a subject scope working', function () {
    $user = User::create(['name' => 'Grace Hopper', 'email' => 'grace@example.com']);

    $request = app(DeletionScheduler::class)->requestDeletion($user);
    GdprDeletion::query()->update(['scheduled_for' => now()->subDay()]);

    app(DeletionScheduler::class)->processDueDeletions();

    expect(DB::table('users')->where('id', $user->id)->exists())->toBeFalse()
        ->and(GdprRequest::find($request->id)->status)->toBe(RequestStatus::Completed);
});
