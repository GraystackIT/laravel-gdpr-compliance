<?php

declare(strict_types=1);

namespace GraystackIt\Gdpr\Contracts;

interface Anonymizer
{
    /**
     * Transform the given value into a non-identifying replacement.
     *
     * @param  mixed  $value  The original value from the model attribute.
     * @param  array<string, mixed>  $config  Per-field configuration from the profile.
     * @return mixed The anonymized replacement value.
     */
    public function anonymize(mixed $value, array $config = []): mixed;
}
