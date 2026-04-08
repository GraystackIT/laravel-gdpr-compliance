<?php

declare(strict_types=1);

namespace GraystackIt\Gdpr\Enums;

enum RequestStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Failed = 'failed';

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Completed, self::Cancelled, self::Failed => true,
            default => false,
        };
    }
}
