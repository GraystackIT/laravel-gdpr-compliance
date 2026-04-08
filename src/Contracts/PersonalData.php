<?php

declare(strict_types=1);

namespace GraystackIt\Gdpr\Contracts;

use GraystackIt\Gdpr\Support\PersonalDataBlueprint;

interface PersonalData
{
    /**
     * Configure the personal data blueprint for this model.
     *
     * The method receives an empty builder and must return a configured
     * builder. The framework then calls build() internally to freeze it
     * into an immutable value object.
     */
    public function personalData(PersonalDataBlueprint $blueprint): PersonalDataBlueprint;
}
