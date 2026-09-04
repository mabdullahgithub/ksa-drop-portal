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

        // A successful delivery is recorded once, at the point of sync
        // (ProcessShopifyWebhookJob). The rejection paths below are the only
        // ones that would otherwise vanish without a trace, so they log; a
        // per-delivery "received" line would just be noise next to them.
        if (! $this->shopify->verifyWebhookHmac($rawBody, $hmacHeader)) {
            Log::channel('shopify')->warning('Shopify webhook rejected — HMAC mismatch', ['shop' => $shop, 'topic' => $topic]);

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
            Log::channel('shopify')->warning('Shopify webhook rejected — shop mismatch', [
                'header_shop' => $shop, 'body_shop' => $bodyShop, 'topic' => $topic,
            ]);

            return response('Shop mismatch', 401);
        }

        // Pinned to the database queue rather than the app's default connection,
        // which is `sync` in production — and under `sync` this "dispatch" runs
        // the whole order sync inline: mapping, upsert, line-item replacement,
        // all before Shopify gets its 200. That put the p90 response at ~1.1s
        // per delivery, and on shared hosting a webhook holding a PHP worker
        // for a full second is what actually loses deliveries: when two arrive
        // at once the spare connection is dropped, and Shopify records it as
        // "No response from app" at ~1s — not a timeout, a refused connection.
        //
        // Queued, this handler does an HMAC check and one INSERT, so the worker
        // is free again in tens of milliseconds instead of a second.
        //
        // Only this job moves. Mail and notifications stay on the default
        // connection, so nothing else in the app changes behaviour — and mail
        // in particular keeps sending inline rather than depending on a worker.
        ProcessShopifyWebhookJob::dispatch($shop, $topic, $payload)->onConnection('database');

        return response('OK', 200);
    }
}
