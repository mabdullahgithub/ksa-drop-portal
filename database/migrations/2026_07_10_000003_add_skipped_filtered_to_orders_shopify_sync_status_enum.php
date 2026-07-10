<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Adds 'skipped_filtered' to the shopify_sync_status enum: the order was
     * synced from Shopify but did not match the merchant's sync filter rules
     * (status / tags / payment method). Hidden from all normal views by the
     * Order 'shopify_visible' global scope, same as pending_review/dismissed,
     * and never auto-promoted.
     *
     * Raw ALTER because doctrine/dbal is not installed (required for ->change()).
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE orders MODIFY COLUMN shopify_sync_status ENUM('pending_review','approved','dismissed','skipped_filtered') NULL DEFAULT NULL");
    }

    public function down(): void
    {
        // Reassign skipped_filtered rows before narrowing the enum or the ALTER fails.
        DB::statement("UPDATE orders SET shopify_sync_status = NULL WHERE shopify_sync_status = 'skipped_filtered'");
        DB::statement("ALTER TABLE orders MODIFY COLUMN shopify_sync_status ENUM('pending_review','approved','dismissed') NULL DEFAULT NULL");
    }
};
