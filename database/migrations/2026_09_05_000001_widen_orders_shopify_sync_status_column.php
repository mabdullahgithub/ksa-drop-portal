<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Replace the shopify_sync_status ENUM with a plain string.
 *
 * The column has already needed one ALTER to admit a new value
 * (2026_07_10_000003 for 'skipped_filtered'), and every future status would
 * need another — a schema migration to add a word to a list the application
 * already validates.
 *
 * The sharper problem is that the original migration skipped anything that was
 * not MySQL, on the reasoning that "sqlite has no ENUM type — the column is
 * plain TEXT there and already accepts any value". That is not what Laravel
 * does: $table->enum() on sqlite emits a CHECK constraint, so the test database
 * kept enforcing the *original* three values. 'skipped_filtered' could not be
 * written there at all, which meant the main outcome of the sync-filter feature
 * — an order the merchant's filters reject — had never once been exercised by a
 * test, on any driver.
 *
 * A varchar removes both problems and costs nothing: the values are produced by
 * ShopifyService::evaluateSyncFilters and read back by the Order model's
 * shopify_visible scope, neither of which relies on the database refusing an
 * unknown string.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Raw MODIFY on MySQL: doctrine/dbal is not installed, and this keeps
        // the existing index rather than dropping and rebuilding it.
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE orders MODIFY COLUMN shopify_sync_status VARCHAR(32) NULL DEFAULT NULL');

            return;
        }

        // sqlite has no in-place column change; Laravel rebuilds the table,
        // which is what actually drops the stale CHECK constraint.
        Schema::table('orders', function (Blueprint $table) {
            $table->string('shopify_sync_status', 32)->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        // Reassign anything outside the original list before narrowing, or the
        // ALTER fails on rows the old enum cannot represent.
        DB::table('orders')
            ->whereNotNull('shopify_sync_status')
            ->whereNotIn('shopify_sync_status', ['pending_review', 'approved', 'dismissed', 'skipped_filtered'])
            ->update(['shopify_sync_status' => null]);

        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE orders MODIFY COLUMN shopify_sync_status ENUM('pending_review','approved','dismissed','skipped_filtered') NULL DEFAULT NULL");
    }
};
