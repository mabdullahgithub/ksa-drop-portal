<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();

            // Polymorphic: either a catalogue Product or a fulfilment
            // client's ClientProduct — the two separate stock pools.
            $table->morphs('stockable');

            // Signed delta actually applied: negative deducts, positive restocks.
            $table->integer('quantity');
            // Pool level after this movement, so the ledger can be audited
            // against the product row without replaying every row.
            $table->integer('balance_after');

            $table->string('reason')->index();
            $table->text('note')->nullable();

            /**
             * Idempotency key — this index is what actually makes stock
             * movement exactly-once, rather than the application-level
             * check-then-act that precedes it.
             *
             * Automatic movements derive a deterministic key from the
             * shipment, direction and order line, so any replay — a courier
             * re-pushing the same webhook, a poll racing a push, a retried
             * request whose first attempt committed after we timed out, two
             * queue workers on the same payload — collides here and is
             * rejected by the database no matter which process it came from.
             *
             * Manual adjustments store NULL: an admin correcting a count is
             * legitimately repeatable, and both MySQL and SQLite permit
             * unlimited NULLs in a unique index.
             */
            $table->string('dedupe_key')->nullable()->unique();

            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('shipment_id')->nullable()->constrained()->nullOnDelete();
            // Who made a manual adjustment; null for automatic movements.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamps();

            $table->index(['shipment_id', 'reason']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
