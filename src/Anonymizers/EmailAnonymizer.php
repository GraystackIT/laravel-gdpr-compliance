<?php

declare(strict_types=1);

namespace GraystackIt\Gdpr\Anonymizers;

use GraystackIt\Gdpr\Contracts\Anonymizer;

class EmailAnonymizer implements Anonymizer
{
    public function anonymize(mixed $value, array $config = []): mixed
    {
        if ($value === null) {
            return null;
        }

        $domain = $config['domain'] ?? 'example.invalid';
        $token = bin2hex(random_bytes(6));

        return "anonymized_{$token}@{$domain}";
    }
}
