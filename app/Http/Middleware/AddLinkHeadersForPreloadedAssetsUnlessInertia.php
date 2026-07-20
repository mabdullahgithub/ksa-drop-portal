<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

/**
 * Adds Link: rel=preload headers only on full page loads. Inertia SPA
 * navigations are XHR responses — the browser still honors preload headers
 * on those, re-fetches assets the page has already applied, and logs a
 * "preloaded but not used" console warning on every navigation.
 */
class AddLinkHeadersForPreloadedAssetsUnlessInertia extends AddLinkHeadersForPreloadedAssets
{
    public function handle($request, $next, $limit = null)
    {
        if ($request->header('X-Inertia')) {
            return $next($request);
        }

        return parent::handle($request, $next, $limit);
    }
}
