<?php

declare(strict_types=1);

namespace GraystackIt\Gdpr\Events;

use GraystackIt\Gdpr\Models\GdprRequest;
use Illuminate\Foundation\Events\Dispatchable;

class PersonalDataExported
{
    use Dispatchable;

    public function __construct(public GdprRequest $request) {}
}
