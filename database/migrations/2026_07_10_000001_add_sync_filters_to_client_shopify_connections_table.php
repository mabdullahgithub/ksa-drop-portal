<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Merchant-configured order sync filters, managed from the embedded
     * Shopify admin app. Null = no restrictions (process every order).
     *
     * Shape: {
     *   financial_statuses:   string[]|null,  // null/empty = all
     *   fulfillment_statuses: string[]|null,  // null/empty = all
     *   tags_include:         string[],       // empty = no include restriction
     *   tags_exclude:         string[],       // empty = nothing excluded
     *   payment_method:       'all'|'cod'|'prepaid'
     * }
     */
    public function up(): void
    {
        Schema::table('client_shopify_connections', function (Blueprint $table) {
            $table->json('sync_filters')->nullable()->after('sync_mode');
        });
    }

    public function down(): void
    {
        Schema::table('client_shopify_connections', function (Blueprint $table) {
            $table->dropColumn('sync_filters');
        });
    }
};
