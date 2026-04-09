<?php

declare(strict_types=1);

namespace GraystackIt\Gdpr\Anonymizers;

use GraystackIt\Gdpr\Contracts\Anonymizer;

class IpAddressAnonymizer implements Anonymizer
{
    public function anonymize(mixed $value, array $config = []): mixed
    {
        if ($value === null || ! is_string($value)) {
            return null;
        }

        $mask = $config['mask'] ?? 'octet';

        if (filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $this->maskIpv4($value, $mask);
        }

        if (filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return $this->maskIpv6($value);
        }

        return null;
    }

    protected function maskIpv4(string $ip, string $mask): string
    {
        $parts = explode('.', $ip);

        return match ($mask) {
            'full' => '0.0.0.0',
            'half' => "{$parts[0]}.{$parts[1]}.0.0",
            default => "{$parts[0]}.{$parts[1]}.{$parts[2]}.0", // octet
        };
    }

    protected function maskIpv6(string $ip): string
    {
        $expanded = inet_ntop(inet_pton($ip));
        $groups = explode(':', $expanded);
        $kept = array_slice($groups, 0, 4);
        $kept = array_pad($kept, 8, '0');

        return implode(':', $kept);
    }
}
