<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessShopifyWebhookJob;
use App\Services\ShopifyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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

        // Log every delivery on arrival. Rejections below return non-200 with
        // no other trace, which made a webhook that never turned into an order
        // impossible to distinguish from one that never arrived. One INFO line
        // per delivery is cheap and makes the whole pipeline observable.
        Log::info('Shopify webhook received', ['shop' => $shop, 'topic' => $topic]);

        if (! $this->shopify->verifyWebhookHmac($rawBody, $hmacHeader)) {
            Log::warning('Shopify webhook rejected — HMAC mismatch', ['shop' => $shop, 'topic' => $topic]);

            return response('Unauthorized', 401);
        }

        $payload = json_decode($rawBody, true) ?: [];

        // The HMAC signs only the body, never the X-Shopify-Shop-Domain header.
        // For topics whose signed body carries the shop domain — every GDPR
        // compliance topic and app/uninstalled — require it to match the header.
        // Otherwise a replayed-but-validly-signed webhook could be retargeted at
        // another merchant's store just by swapping the (unsigned) header, and
        // these are exactly the topics that redact PII and disconnect a store.
        $bodyShop = $payload['shop_domain'] ?? $payload['myshopify_domain'] ?? null;

        if ($bodyShop !== null && ! hash_equals(strtolower((string) $bodyShop), strtolower($shop))) {
            Log::warning('Shopify webhook rejected — shop mismatch', [
                'header_shop' => $shop, 'body_shop' => $bodyShop, 'topic' => $topic,
            ]);

            return response('Shop mismatch', 401);
        }

        ProcessShopifyWebhookJob::dispatch($shop, $topic, $payload);

        return response('OK', 200);
    }
}
