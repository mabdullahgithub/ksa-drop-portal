<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Shopify fulfillment this shipment created, when the order came from a
 * connected store.
 *
 * Kept because cancelling one needs it: fulfillmentCancel takes the
 * fulfillment's own id, and nothing else on our side can produce it. Without it
 * a returned parcel would leave the merchant's order showing as fulfilled, with
 * a tracking number their customer can still follow, for good.
 *
 * Also the flag that stops a second fulfillment being created for a shipment
 * that is re-saved or retried.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->string('shopify_fulfillment_id')->nullable()->after('tracking_number');
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn('shopify_fulfillment_id');
        });
    }
};
