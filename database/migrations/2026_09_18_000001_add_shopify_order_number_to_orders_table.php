<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Shopify's own number for an order (#1001 → 1001), kept apart from ours.
 *
 * Until now the portal number was always short_id + Shopify's number, so one
 * could be read off the other. That stops holding once a client moves to a
 * second store: Shopify numbers every store from #1001, so the new store's
 * orders continue the client's sequence instead (see ShopifyOrderWriter), and
 * this column is what still ties them back to the order in Shopify admin.
 *
 * Existing Shopify orders are backfilled from their order_number, which for
 * every one of them is still the client prefix followed by Shopify's number.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('shopify_order_number')->nullable()->after('shopify_order_id');
        });

        DB::table('orders')
            ->join('clients', 'clients.id', '=', 'orders.client_id')
            ->where('orders.source', 'shopify')
            ->whereNull('orders.shopify_order_number')
            ->select('orders.id', 'orders.order_number', 'clients.id as client_id', 'clients.short_id')
            ->orderBy('orders.id')
            ->chunk(500, function ($rows) {
                foreach ($rows as $row) {
                    $prefix = $row->short_id ?: 'CL' . $row->client_id;

                    if (preg_match('/^' . preg_quote($prefix, '/') . '(\d+)$/', $row->order_number, $m)) {
                        DB::table('orders')->where('id', $row->id)->update(['shopify_order_number' => (int) $m[1]]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('shopify_order_number');
        });
    }
};
