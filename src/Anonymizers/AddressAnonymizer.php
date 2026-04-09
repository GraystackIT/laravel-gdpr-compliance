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

        // For JSON/array addresses, recursively wipe all scalar leaves but keep structure.
        if (is_array($value)) {
            return $this->redactArray($value);
        }

        return $config['placeholder'] ?? '[REDACTED ADDRESS]';
    }

    /**
     * @param  array<mixed>  $data
     * @return array<mixed>
     */
    protected function redactArray(array $data): array
    {
        $result = [];
        foreach ($data as $key => $item) {
            $result[$key] = is_array($item) ? $this->redactArray($item) : '[REDACTED]';
        }

        return $result;
    }
}
