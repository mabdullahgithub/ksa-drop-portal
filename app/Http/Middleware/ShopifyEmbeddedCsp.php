<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Allows the embedded app routes (and only those) to be framed by the
 * Shopify Admin. The rest of the app carries no frame-ancestors policy and
 * keeps default browser framing behavior.
 */
class ShopifyEmbeddedCsp
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set(
            'Content-Security-Policy',
            'frame-ancestors https://admin.shopify.com https://*.myshopify.com;'
        );

        return $response;
    }
}
