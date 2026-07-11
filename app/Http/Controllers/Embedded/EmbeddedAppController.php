<?php

namespace App\Http\Controllers\Embedded;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Serves the HTML shell for the embedded Shopify Admin app. This URL is
 * configured as the "App URL" in the Shopify Partner Dashboard — Shopify
 * iframes it with ?shop=...&host=...&embedded=1 query params.
 */
class EmbeddedAppController extends Controller
{
    public function index(Request $request)
    {
        $portalUrl = rtrim((string) config('services.shopify.portal_url'), '/');

        return view('embedded', [
            'shop'           => (string) $request->query('shop', ''),
            'host'           => (string) $request->query('host', ''),
            'portalLoginUrl' => $portalUrl . '/login',
        ]);
    }
}
