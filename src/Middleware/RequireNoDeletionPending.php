<?php

declare(strict_types=1);

namespace GraystackIt\Gdpr\Middleware;

use Closure;
use GraystackIt\Gdpr\Support\GdprManager;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks requests from users whose account is scheduled for deletion.
 *
 * Usage on auth routes:
 *   Route::middleware('gdpr.no-deletion-pending')->group(...)
 */
class RequireNoDeletionPending
{
    public function __construct(protected GdprManager $gdpr) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $this->gdpr->isDeletionPending($user)) {
            return $this->denied($request);
        }

        return $next($request);
    }

    protected function denied(Request $request): Response
    {
        $message = __('gdpr::gdpr.middleware.deletion_pending');

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 423);
        }

        return response($message, 423);
    }
}
