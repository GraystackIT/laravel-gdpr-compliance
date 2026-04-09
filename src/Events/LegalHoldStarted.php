<?php

declare(strict_types=1);

namespace GraystackIt\Gdpr\Events;

use GraystackIt\Gdpr\Models\GdprDeletion;
use Illuminate\Foundation\Events\Dispatchable;

class LegalHoldStarted
{
    use Dispatchable;

    public function __construct(public GdprDeletion $deletion) {}
}
