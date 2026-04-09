<?php

declare(strict_types=1);

namespace GraystackIt\Gdpr\Middleware;

use Closure;
use GraystackIt\Gdpr\Support\ConsentCookieManager;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensures every response carries the consent cookie if one is
 * present on the request. Acts as a read-through pass so downstream
 * responses always include the most recent cookie state.
 */
class ApplyConsentCookies
{
    public function __construct(protected ConsentCookieManager $cookies) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $data = $this->cookies->read($request);
        if ($data !== null) {
            $cookie = $this->cookies->build($data, $data['policy_version'] ?? null);
            $response->headers->setCookie($cookie);
        }

        return $response;
    }
}
