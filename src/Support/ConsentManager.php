<?php

declare(strict_types=1);

namespace GraystackIt\Gdpr\Support;

use GraystackIt\Gdpr\Enums\ConsentPurpose;
use GraystackIt\Gdpr\Models\Consent;
use Illuminate\Database\Eloquent\Model;

/**
 * Append-only consent store. The current effective consent for a subject
 * and purpose is the most recent row with that (subject, purpose) tuple.
 *
 * Never updates existing rows. Grant and withdraw both insert new rows.
 */
class ConsentManager
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function grant(Model $subject, ConsentPurpose $purpose, ?string $source = null, array $context = []): Consent
    {
        return Consent::create([
            'subject_type' => $subject::class,
            'subject_id' => $subject->getKey(),
            'purpose' => $purpose->value,
            'action' => 'grant',
            'source' => $source,
            'context' => $context !== [] ? $context : null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function withdraw(Model $subject, ConsentPurpose $purpose, ?string $source = null, array $context = []): Consent
    {
        return Consent::create([
            'subject_type' => $subject::class,
            'subject_id' => $subject->getKey(),
            'purpose' => $purpose->value,
            'action' => 'withdraw',
            'source' => $source,
            'context' => $context !== [] ? $context : null,
        ]);
    }

    public function hasConsent(Model $subject, ConsentPurpose $purpose): bool
    {
        if (! $purpose->requiresConsent()) {
            return true;
        }

        $latest = Consent::query()
            ->where('subject_type', $subject::class)
            ->where('subject_id', $subject->getKey())
            ->where('purpose', $purpose->value)
            ->latest('created_at')
            ->first();

        return $latest?->action === 'grant';
    }

    /**
     * Return the current effective consent state for all purposes.
     *
     * @return array<string, bool>
     */
    public function statusFor(Model $subject): array
    {
        $status = [];
        foreach (ConsentPurpose::cases() as $purpose) {
            $status[$purpose->value] = $this->hasConsent($subject, $purpose);
        }

        return $status;
    }
}
