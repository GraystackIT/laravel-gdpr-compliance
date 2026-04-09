<?php

declare(strict_types=1);

use GraystackIt\Gdpr\Enums\ConsentPurpose;
use GraystackIt\Gdpr\Models\Consent;
use GraystackIt\Gdpr\Support\ConsentManager;
use Workbench\App\Models\User;

beforeEach(function () {
    $this->user = User::create(['name' => 'Ada', 'email' => 'ada@example.com']);
    $this->cm = new ConsentManager;
});

it('returns true for Necessary without any record', function () {
    expect($this->cm->hasConsent($this->user, ConsentPurpose::Necessary))->toBeTrue();
});

it('returns false for optional purposes without a grant', function () {
    expect($this->cm->hasConsent($this->user, ConsentPurpose::Marketing))->toBeFalse();
});

it('returns true after grant and false after withdraw (latest wins)', function () {
    $this->cm->grant($this->user, ConsentPurpose::Analytics, 'cookie_banner');

    expect($this->cm->hasConsent($this->user, ConsentPurpose::Analytics))->toBeTrue();

    $this->cm->withdraw($this->user, ConsentPurpose::Analytics, 'profile_settings');

    expect($this->cm->hasConsent($this->user, ConsentPurpose::Analytics))->toBeFalse();
});

it('handles grant -> withdraw -> grant flow correctly', function () {
    // Manually insert with controlled timestamps so the ordering is deterministic
    // even when all inserts happen within the same second.
    Consent::create([
        'subject_type' => User::class,
        'subject_id' => $this->user->id,
        'purpose' => 'marketing',
        'action' => 'grant',
        'created_at' => now()->subMinutes(2),
    ]);
    Consent::create([
        'subject_type' => User::class,
        'subject_id' => $this->user->id,
        'purpose' => 'marketing',
        'action' => 'withdraw',
        'created_at' => now()->subMinute(),
    ]);
    Consent::create([
        'subject_type' => User::class,
        'subject_id' => $this->user->id,
        'purpose' => 'marketing',
        'action' => 'grant',
        'created_at' => now(),
    ]);

    expect($this->cm->hasConsent($this->user, ConsentPurpose::Marketing))->toBeTrue();
});

it('returns status for all purposes', function () {
    $this->cm->grant($this->user, ConsentPurpose::Analytics);

    $status = $this->cm->statusFor($this->user);

    expect($status['necessary'])->toBeTrue()
        ->and($status['analytics'])->toBeTrue()
        ->and($status['marketing'])->toBeFalse();
});
