<?php

declare(strict_types=1);

namespace GraystackIt\Gdpr\Anonymizers;

use GraystackIt\Gdpr\Contracts\Anonymizer;

class AddressAnonymizer implements Anonymizer
{
    public function anonymize(mixed $value, array $config = []): mixed
    {
        if ($value === null) {
            return null;
        }

        // For JSON/array addresses, wipe component fields but keep structure.
        if (is_array($value)) {
            return array_map(fn () => '[REDACTED]', $value);
        }

        return $config['placeholder'] ?? '[REDACTED ADDRESS]';
    }
}
