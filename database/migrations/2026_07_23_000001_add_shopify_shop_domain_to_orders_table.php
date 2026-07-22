<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Record which Shopify store each synced order came from.
 *
 * Orders were only attributable to a shop indirectly, through their client's
 * connection row. That broke GDPR shop/redact as soon as a connection could be
 * unlinked: releasing client_id (portal disconnect) left redactShop() with no
 * way to find the shop's orders, so it silently scrubbed nothing. Storing the
 * domain on the order makes erasure independent of the account link, and
 * correct for a client who had more than one store over time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('shopify_shop_domain')->nullable()->after('shopify_order_id')->index();
        });

        // Backfill from the existing links so already-synced orders stay
        // erasable. Rows whose connection is already gone can't be attributed
        // to a shop — but shop/redact only fires for a connection that exists,
        // so they were unreachable before this migration too.
        DB::table('client_shopify_connections')
            ->whereNotNull('client_id')
            ->get(['client_id', 'shop_domain'])
            ->each(function ($connection) {
                DB::table('orders')
                    ->where('client_id', $connection->client_id)
                    ->where('source', 'shopify')
                    ->whereNull('shopify_shop_domain')
                    ->update(['shopify_shop_domain' => $connection->shop_domain]);
            });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['shopify_shop_domain']);
            $table->dropColumn('shopify_shop_domain');
        });
    }
};
