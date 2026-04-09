<?php

declare(strict_types=1);

namespace GraystackIt\Gdpr\Traits;

/**
 * Marker trait for any Eloquent model that holds personal data.
 *
 * The package does not add any runtime behavior to the host model — no
 * observers, no global scopes, no forced SoftDeletes. The trait simply
 * signals intent; the ModelRegistry discovers these models via
 * config('gdpr.models') and calls personalData() to build the profile.
 */
trait HasPersonalData
{
    //
}
