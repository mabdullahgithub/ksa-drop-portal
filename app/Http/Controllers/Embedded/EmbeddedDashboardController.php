<?php

namespace App\Http\Controllers\Embedded;

use App\Http\Controllers\Controller;
use App\Models\ClientShopifyConnection;
use App\Services\EmbeddedPayloadService;
use Illuminate\Http\Request;

class EmbeddedDashboardController extends Controller
{
    public function __construct(private EmbeddedPayloadService $payload) {}

    /**
     * Sync stats + recent orders for the embedded dashboard. The connection is
     * resolved by the shopify.session middleware from the session token.
     *
     * The payload itself is built by EmbeddedPayloadService, shared with the
     * Blade shell — which inlines the same JSON on the initial load so the
     * first paint doesn't have to wait for this request.
     */
    public function index(Request $request)
    {
        /** @var ClientShopifyConnection $connection */
        $connection = $request->attributes->get('shopify_connection');

        return response()->json($this->payload->dashboard($connection));
    }
}
