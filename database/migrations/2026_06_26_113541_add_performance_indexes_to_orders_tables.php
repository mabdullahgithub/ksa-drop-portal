<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Missing index on client_id (used in filtering and scoping queries).
            if (! $this->indexExists('orders', 'orders_client_id_index')) {
                $table->index('client_id');
            }

            // Composite index for common queries: "orders for this client, ordered by date".
            if (! $this->indexExists('orders', 'orders_client_id_created_at_index')) {
                $table->index(['client_id', 'created_at']);
            }
        });

        Schema::table('order_items', function (Blueprint $table) {
            // Index on lineitem_sku (used in updateOrCreate lookups and inventory tracking).
            if (! $this->indexExists('order_items', 'order_items_lineitem_sku_index')) {
                $table->index('lineitem_sku');
            }

            // Composite index for efficient SKU lookups within an order.
            if (! $this->indexExists('order_items', 'order_items_order_id_lineitem_sku_index')) {
                $table->index(['order_id', 'lineitem_sku']);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndexIfExists('orders_client_id_index');
            $table->dropIndexIfExists('orders_client_id_created_at_index');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndexIfExists('order_items_lineitem_sku_index');
            $table->dropIndexIfExists('order_items_order_id_lineitem_sku_index');
        });
    }

    /**
     * Check if an index exists on a table.
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $indexes = DB::select("SHOW INDEX FROM {$table}");
        return collect($indexes)->contains('Key_name', $indexName);
    }
};
