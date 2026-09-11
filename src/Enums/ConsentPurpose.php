<?php

declare(strict_types=1);

namespace GraystackIt\Gdpr\Enums;

enum ConsentPurpose: string
{
    case Necessary = 'necessary';
    case Analytics = 'analytics';
    case Marketing = 'marketing';
    case EmbeddedContent = 'embedded_content';
    case Personalization = 'personalization';

    /**
     * Keeping somebody's data on file after the purpose it was given for has ended.
     *
     * The case a recruiting talent pool needs: an application is processed on a legitimate
     * interest and deleted when the vacancy is closed, and keeping it for the *next* vacancy is
     * a different purpose the person has to agree to — which also means the consent is
     * withdrawable and the deletion deadline comes back when it is.
     */
    case TalentPool = 'talent_pool';

    public function label(): string
    {
        return match ($this) {
            self::Necessary => 'Strictly necessary',
            self::Analytics => 'Analytics',
            self::Marketing => 'Marketing',
            self::EmbeddedContent => 'Embedded content',
            self::Personalization => 'Personalization',
            self::TalentPool => 'Talent pool',
        };
    }

    /**
     * Whether this purpose legally requires an opt-in consent from the user.
     *
     * "Necessary" cookies/processing do not require consent under DSGVO
     * because they are essential for the service the user requested.
     */
    public function requiresConsent(): bool
    {
        return $this !== self::Necessary;
    }

    public function isOptional(): bool
    {
        return $this->requiresConsent();
    }

    /**
     * Accept a value that may be a ConsentPurpose, a string, or null,
     * and return the matching enum or null.
     */
    public static function fromMixed(mixed $value): ?self
    {
        return match (true) {
            $value instanceof self => $value,
            is_string($value) => self::tryFrom($value),
            default => null,
        };
    }
}
