<?php

declare(strict_types=1);

namespace GraystackIt\Gdpr\Enums;

enum RetentionMode: string
{
    /** After grace period, the row is hard-deleted. */
    case Delete = 'delete';

    /** After grace period, fields are wiped via anonymizers; the row stays. */
    case Anonymize = 'anonymize';

    /** After grace period, fields are wiped and the row is retained
     *  until hold_until, then hard-deleted (mandatory). */
    case LegalHold = 'legal_hold';
}
