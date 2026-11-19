<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Track the one-time plan purchase made through the Shopify Billing API
 * (appPurchaseOneTimeCreate). The purchase id is the Shopify GID; status
 * mirrors Shopify's AppPurchaseStatus, lower-cased (pending, active, declined,
 * expired). `plan_purchased_at` is set only once Shopify reports it ACTIVE.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_shopify_connections', function (Blueprint $table) {
            $table->string('plan_purchase_id')->nullable()->after('fulfillment_registered_at');
            $table->string('plan_status', 20)->nullable()->after('plan_purchase_id');
            $table->timestamp('plan_purchased_at')->nullable()->after('plan_status');
        });
    }

    public function down(): void
    {
        Schema::table('client_shopify_connections', function (Blueprint $table) {
            $table->dropColumn(['plan_purchase_id', 'plan_status', 'plan_purchased_at']);
        });
    }
};
