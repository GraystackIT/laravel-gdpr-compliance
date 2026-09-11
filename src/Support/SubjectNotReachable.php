<?php

declare(strict_types=1);

namespace GraystackIt\Gdpr\Support;

use RuntimeException;

/**
 * Thrown when a subject row still exists but the package cannot reach it: the
 * model's global scopes hide it (tenancy, published flags, ...) and no
 * scopePersonalDataForSubject() removes them.
 *
 * Processing the deletion anyway would erase nothing and still record the
 * erasure as done, so the package refuses the row instead.
 */
class SubjectNotReachable extends RuntimeException
{
    public static function for(string $subjectType, int|string $subjectId): self
    {
        return new self(sprintf(
            'Subject %s [%s] exists but is not reachable through its registered scope — global '
            .'scopes on the model hide it. Define scopePersonalDataForSubject() on %s (or register '
            .'a scope class for it in config("gdpr.models")) that removes them.',
            $subjectType,
            $subjectId,
            $subjectType,
        ));
    }
}
