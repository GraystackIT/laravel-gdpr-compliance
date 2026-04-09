<?php

declare(strict_types=1);

namespace GraystackIt\Gdpr\Support;

use GraystackIt\Gdpr\Enums\ConsentPurpose;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Reads and writes the consent cookie. The cookie is a JSON blob holding
 * per-purpose boolean grants, the policy version, and a timestamp.
 */
class ConsentCookieManager
{
    public const COOKIE_NAME = 'gdpr_consent';

    public function __construct(
        protected int $lifetimeDays = 180,
        protected ?string $domain = null,
        protected bool $secure = true,
        protected string $sameSite = 'lax',
    ) {}

    /**
     * Parse the consent cookie from the request. Returns null if absent or invalid.
     *
     * @return array<string, mixed>|null
     */
    public function read(Request $request): ?array
    {
        $raw = $request->cookie(self::COOKIE_NAME);
        if (! is_string($raw) || $raw === '') {
            return null;
        }

        $data = json_decode($raw, true);
        if (! is_array($data)) {
            return null;
        }

        return $data;
    }

    public function has(Request $request, ConsentPurpose $purpose): bool
    {
        if (! $purpose->requiresConsent()) {
            return true;
        }

        $data = $this->read($request);
        if ($data === null) {
            return false;
        }

        return (bool) ($data[$purpose->value] ?? false);
    }

    /**
     * Build a Cookie carrying the given per-purpose grants plus meta fields.
     *
     * @param  array<string, bool>  $grants
     */
    public function build(array $grants, ?string $policyVersion = null): Cookie
    {
        $payload = [
            ConsentPurpose::Necessary->value => true,
            ConsentPurpose::Analytics->value => false,
            ConsentPurpose::Marketing->value => false,
            ConsentPurpose::EmbeddedContent->value => false,
            ConsentPurpose::Personalization->value => false,
        ];

        foreach ($grants as $key => $value) {
            $purpose = ConsentPurpose::fromMixed($key);
            if ($purpose !== null) {
                $payload[$purpose->value] = (bool) $value;
            }
        }

        $payload['policy_version'] = $policyVersion;
        $payload['updated_at'] = now()->toIso8601String();

        return new Cookie(
            name: self::COOKIE_NAME,
            value: (string) json_encode($payload),
            expire: now()->addDays($this->lifetimeDays)->getTimestamp(),
            path: '/',
            domain: $this->domain,
            secure: $this->secure,
            httpOnly: false,
            raw: false,
            sameSite: $this->sameSite,
        );
    }
}
