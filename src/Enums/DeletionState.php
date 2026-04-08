<?php

declare(strict_types=1);

namespace GraystackIt\Gdpr\Enums;

enum DeletionState: string
{
    case PendingGrace = 'pending_grace';
    case PendingLegalHold = 'pending_legal_hold';
    case Anonymized = 'anonymized';
    case Erased = 'erased';
    case Cancelled = 'cancelled';

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Anonymized, self::Erased, self::Cancelled => true,
            default => false,
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return match ([$this, $next]) {
            [self::PendingGrace, self::Cancelled],
            [self::PendingGrace, self::Erased],
            [self::PendingGrace, self::Anonymized],
            [self::PendingGrace, self::PendingLegalHold],
            [self::PendingLegalHold, self::Erased] => true,
            default => false,
        };
    }
}
