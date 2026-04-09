<?php

declare(strict_types=1);

namespace GraystackIt\Gdpr\Traits;

/**
 * Marker trait for models that can initiate GDPR requests on themselves.
 *
 * The high-level API methods (requestDeletion, cancelDeletion, requestExport,
 * etc.) are added in Phase 8 when the GdprManager is wired up.
 */
trait IsPersonalDataSubject
{
    //
}
