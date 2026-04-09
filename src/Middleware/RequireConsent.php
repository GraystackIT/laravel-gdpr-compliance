<?php

declare(strict_types=1);

namespace GraystackIt\Gdpr\Middleware;

use Closure;
use GraystackIt\Gdpr\Enums\ConsentPurpose;
use GraystackIt\Gdpr\Support\ConsentCookieManager;
use GraystackIt\Gdpr\Support\ConsentManager;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks requests that lack consent for the given purposes.
 *
 * Usage: Route::middleware('gdpr.consent:analytics,marketing')
 *
 * Returns HTTP 451 Unavailable For Legal Reasons for JSON requests.
 * For HTML requests, returns 451 with a plain message. The host app
 * can customize by catching the response or publishing views.
 */
class RequireConsent
{
    public function __construct(
        protected ConsentManager $consentManager,
        protected ConsentCookieManager $cookieManager,
    ) {}

    public function handle(Request $request, Closure $next, string ...$purposes): Response
    {
        foreach ($purposes as $purposeString) {
            $purpose = ConsentPurpose::tryFrom($purposeString);
            if ($purpose === null) {
                continue;
            }

            if (! $purpose->requiresConsent()) {
                continue;
            }

            if (! $this->hasConsent($request, $purpose)) {
                return $this->denied($request, $purpose);
            }
        }

        return $next($request);
    }

    protected function hasConsent(Request $request, ConsentPurpose $purpose): bool
    {
        $user = $request->user();

        if ($user !== null && $this->consentManager->hasConsent($user, $purpose)) {
            return true;
        }

        return $this->cookieManager->has($request, $purpose);
    }

    protected function denied(Request $request, ConsentPurpose $purpose): Response
    {
        $message = __('gdpr::gdpr.middleware.consent_required', ['purpose' => $purpose->value]);

        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'purpose' => $purpose->value], 451);
        }

        return response($message, 451);
    }
}
