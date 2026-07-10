<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The complete, unfiltered Shopify tag list for an order. The existing
     * `tags` column is intentionally narrowed to buyease-related tags by
     * ShopifyService::filterShopifyTags(); merchant-configured tag filters
     * need the full list to evaluate against, so it is stored separately.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->json('shopify_raw_tags')->nullable()->after('tags');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('shopify_raw_tags');
        });
    }
};
