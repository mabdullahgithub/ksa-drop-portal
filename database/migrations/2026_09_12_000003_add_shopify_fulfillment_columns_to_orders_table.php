<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where an order stands in Shopify's fulfillment-order flow.
 *
 * Separate from `fulfillment_status`, which is ours and tracks the parcel:
 * unfulfilled until the courier delivers it. This tracks the request Shopify
 * knows about — submitted, accepted, rejected — and the two move independently.
 * An order can be accepted here and still unfulfilled there for days.
 *
 * Kept on `orders` rather than in a table of its own because a fulfillment
 * order maps one-to-one with the part of the order we fulfil: our merchants
 * import a KSADrop catalogue, so every line on an order we see is ours and
 * Shopify groups them into a single fulfillment order. A merchant splitting an
 * order across several suppliers would need a table; nothing in the portal can
 * produce that today, and inventing it now would be modelling for a case we
 * cannot test.
 *
 * `shopify_fulfillment_order_id` also serves as the idempotency key for
 * requesting: it is set the moment a request is submitted, and every entry
 * point checks it before submitting again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('shopify_fulfillment_order_id')->nullable()->after('shopify_sync_status');
            $table->string('shopify_fulfillment_status', 32)->nullable()->after('shopify_fulfillment_order_id');
            $table->timestamp('shopify_fulfillment_requested_at')->nullable()->after('shopify_fulfillment_status');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'shopify_fulfillment_order_id',
                'shopify_fulfillment_status',
                'shopify_fulfillment_requested_at',
            ]);
        });
    }
};
