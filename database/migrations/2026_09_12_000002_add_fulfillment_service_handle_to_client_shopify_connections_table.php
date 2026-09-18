<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The fulfillment service's handle, as Shopify assigned it.
 *
 * Kept because the product CSV needs it and nothing else will do. Shopify's
 * import matches the "Fulfillment service" column against a slug of the service
 * name — "KSADrop" becomes "ksadrop" — and a value that matches nothing on the
 * store is silently imported as `manual`, leaving the merchant with a catalogue
 * that looks imported but routes no fulfillment orders to us at all.
 *
 * Storing what Shopify returns rather than slugifying the name ourselves means
 * a store where the handle was taken (Shopify appends a suffix) still gets a
 * CSV that matches its own store.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_shopify_connections', function (Blueprint $table) {
            $table->string('fulfillment_service_handle')->nullable()->after('fulfillment_service_id');
        });
    }

    public function down(): void
    {
        Schema::table('client_shopify_connections', function (Blueprint $table) {
            $table->dropColumn('fulfillment_service_handle');
        });
    }
};
