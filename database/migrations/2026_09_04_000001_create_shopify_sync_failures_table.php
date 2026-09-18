<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dead-letter store for Shopify order webhooks that never made it into an order.
 *
 * Before this, a webhook that failed to sync was gone: ProcessShopifyWebhookJob
 * burned its three attempts and died into failed_jobs (which nothing surfaces or
 * drains), and a webhook arriving for an unclaimed / disconnected store was
 * dropped with a log line. Both cases look identical to the merchant — the order
 * is in Shopify but never appears in the portal, with no way to get it back
 * short of an operator running queue:retry by hand.
 *
 * Every failed delivery now parks here with the payload that produced it, so it
 * can be replayed automatically on a backoff schedule, or manually from the
 * portal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shopify_sync_failures', function (Blueprint $table) {
            $table->id();

            $table->string('shop_domain')->index();
            $table->string('topic');

            // Null for topics that carry no order (shop/redact, customers/*).
            $table->string('shopify_order_id')->nullable();

            // Shopify's own order number, kept for display: the portal has no
            // order row to join against, so this is the only human-readable
            // handle the merchant can match against their Shopify admin.
            $table->string('order_number')->nullable();

            // Set once the shop is linked to a client. Left null for a failure
            // parked before the store was claimed — those are scoped by shop.
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();

            // The exact webhook body, so a replay reproduces the original sync
            // rather than a re-fetch of whatever the order looks like now.
            $table->json('payload');

            // no_connection | exception — why the sync did not happen.
            $table->string('reason', 32)->index();
            $table->text('error_message')->nullable();

            // pending | resolved | abandoned
            $table->string('status', 16)->default('pending');

            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamp('resolved_at')->nullable();

            $table->timestamps();

            // One row per order per shop: an order that fails on orders/create
            // and again on orders/updated is one problem, not two. Rows with no
            // order id (GDPR topics) are exempt — MySQL allows repeated NULLs in
            // a unique index — and are de-duplicated on (shop, topic) instead.
            $table->unique(['shop_domain', 'shopify_order_id'], 'shopify_sync_failures_shop_order_unique');

            // The retry sweep's only query: pending rows whose next attempt is
            // due, oldest first. Composite rather than two separate indexes so
            // it is answered from the index alone however large the table's
            // settled backlog grows — the sweep runs every five minutes, and
            // resolved rows vastly outnumber pending ones in steady state.
            $table->index(['status', 'next_attempt_at'], 'shopify_sync_failures_due_index');

            // Cleanup scans, and the portal's per-client listing.
            $table->index(['status', 'resolved_at'], 'shopify_sync_failures_settled_index');
            $table->index(['client_id', 'status'], 'shopify_sync_failures_client_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopify_sync_failures');
    }
};
