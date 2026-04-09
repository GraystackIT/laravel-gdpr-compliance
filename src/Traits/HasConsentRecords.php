<?php

declare(strict_types=1);

namespace GraystackIt\Gdpr\Traits;

use GraystackIt\Gdpr\Enums\ConsentPurpose;
use GraystackIt\Gdpr\Models\Consent;
use GraystackIt\Gdpr\Support\ConsentManager;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Provides morph-many access to the subject's consent history and
 * convenience methods that proxy ConsentManager.
 */
trait HasConsentRecords
{
    public function consents(): MorphMany
    {
        return $this->morphMany(Consent::class, 'subject');
    }

    public function grantConsent(ConsentPurpose $purpose, ?string $source = null, array $context = []): Consent
    {
        return app(ConsentManager::class)->grant($this, $purpose, $source, $context);
    }

    public function withdrawConsent(ConsentPurpose $purpose, ?string $source = null, array $context = []): Consent
    {
        return app(ConsentManager::class)->withdraw($this, $purpose, $source, $context);
    }

    public function hasConsent(ConsentPurpose $purpose): bool
    {
        return app(ConsentManager::class)->hasConsent($this, $purpose);
    }

    /**
     * @return array<string, bool>
     */
    public function consentStatus(): array
    {
        return app(ConsentManager::class)->statusFor($this);
    }
}
