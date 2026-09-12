<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Record the fulfillment service we register on each merchant's store.
 *
 * Registering as a fulfillment service is what makes Shopify assign fulfillment
 * orders to us, which is the whole basis of App Store requirement 5.5.1 — the
 * `fulfillmentOrderSubmitFulfillmentRequest` mutation submits to *the service
 * assigned to a fulfillment order*, so there is nothing to submit to until we
 * are that service.
 *
 * Both ids are Shopify GIDs, stored as given
 * ("gid://shopify/FulfillmentService/…", "gid://shopify/Location/…") because
 * that is the form every GraphQL call takes them in — parsing them down to
 * integers would only mean rebuilding them on the way out.
 *
 * All three are nullable, and that is the normal state for an existing
 * connection rather than an error: the fulfillment scopes are new, so a store
 * connected before this ships holds none of these until the merchant re-grants
 * through managed installation and the backfill sweep reaches it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_shopify_connections', function (Blueprint $table) {
            $table->string('fulfillment_service_id')->nullable()->after('webhooks_registered');
            $table->string('fulfillment_location_id')->nullable()->after('fulfillment_service_id');
            $table->timestamp('fulfillment_registered_at')->nullable()->after('fulfillment_location_id');
        });
    }

    public function down(): void
    {
        Schema::table('client_shopify_connections', function (Blueprint $table) {
            $table->dropColumn([
                'fulfillment_service_id',
                'fulfillment_location_id',
                'fulfillment_registered_at',
            ]);
        });
    }
};
