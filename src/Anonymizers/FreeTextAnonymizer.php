<?php

declare(strict_types=1);

namespace GraystackIt\Gdpr\Anonymizers;

use GraystackIt\Gdpr\Contracts\Anonymizer;

class FreeTextAnonymizer implements Anonymizer
{
    public function anonymize(mixed $value, array $config = []): mixed
    {
        if ($value === null || ! is_string($value)) {
            return $value;
        }

        $replacement = $config['placeholder'] ?? '[REDACTED]';

        // Replace whole text by default; selective replacement via flags.
        if (! ($config['replace_email'] ?? false) && ! ($config['replace_phone'] ?? false) && ! ($config['replace_urls'] ?? false)) {
            return $replacement;
        }

        $text = $value;

        if ($config['replace_email'] ?? false) {
            $text = preg_replace('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', '[EMAIL]', $text);
        }

        if ($config['replace_phone'] ?? false) {
            $text = preg_replace('/\+?[\d\s()\-]{7,}/', '[PHONE]', $text);
        }

        if ($config['replace_urls'] ?? false) {
            $text = preg_replace('#https?://\S+#', '[URL]', $text);
        }

        return $text;
    }
}
