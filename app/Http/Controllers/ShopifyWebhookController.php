<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessShopifyWebhookJob;
use App\Services\ShopifyService;
use Illuminate\Http\Request;

class ShopifyWebhookController extends Controller
{
    public function __construct(private ShopifyService $shopify) {}

    /**
     * Receive a Shopify webhook. Verify HMAC, then hand off to the queue and
     * return 200 immediately — Shopify marks a webhook failed if we take >5s,
     * and retries on any non-200 (so we never return errors for processing bugs).
     */
    public function handle(Request $request)
    {
        $rawBody    = $request->getContent();
        $hmacHeader = (string) $request->header('X-Shopify-Hmac-Sha256', '');
        $shop       = (string) $request->header('X-Shopify-Shop-Domain', '');
        $topic      = (string) $request->header('X-Shopify-Topic', '');

        if (! $this->shopify->verifyWebhookHmac($rawBody, $hmacHeader)) {
            return response('Unauthorized', 401);
        }

        $payload = json_decode($rawBody, true) ?: [];

        ProcessShopifyWebhookJob::dispatch($shop, $topic, $payload);

        return response('OK', 200);
    }
}
