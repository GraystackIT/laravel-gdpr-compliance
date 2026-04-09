<?php

declare(strict_types=1);

namespace GraystackIt\Gdpr\Anonymizers;

use GraystackIt\Gdpr\Contracts\Anonymizer;

class NameAnonymizer implements Anonymizer
{
    public function anonymize(mixed $value, array $config = []): mixed
    {
        if ($value === null) {
            return null;
        }

        $placeholder = $config['placeholder'] ?? 'Anonymous User';

        return $placeholder;
    }
}
