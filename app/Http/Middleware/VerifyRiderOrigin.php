<?php

namespace App\Http\Middleware;

use App\Services\Riders\RiderAuthService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * CSRF protection for the rider app.
 *
 * The rider routes run without sessions, so there is no CSRF token to check.
 * Browsers always send Origin on a cross-site POST, so a write whose Origin
 * (or, failing that, Referer) isn't this host didn't come from the rider app.
 *
 * The phone app (rider-native/) is let through: it runs on its own origin
 * and has no cookie for another site to ride on — it signs each request with
 * a Bearer token, which a browser never adds by itself.
 */
class VerifyRiderOrigin
{
    public function __construct(private RiderAuthService $auth) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethodSafe() || $this->auth->isNativeClient($request)) {
            return $next($request);
        }

        $source = $request->headers->get('Origin') ?: $request->headers->get('Referer');
        $host = $source ? parse_url($source, PHP_URL_HOST) : null;

        if (! $host || strcasecmp($host, $request->getHost()) !== 0) {
            return response()->json(['message' => 'Request refused.', 'code' => 'bad_origin'], 403);
        }

        return $next($request);
    }
}
