<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            /**
             * Fast-path markers, NOT the idempotency guarantee — that lives on
             * stock_movements.dedupe_key. These record that a shipment's stock
             * was moved *completely*, letting the hundreds of repeat status
             * pushes a shipment receives skip the ledger work entirely.
             *
             * Set once the shipment has been through the ledger, whether or
             * not every line moved stock: a line matching no product is
             * skipped, not retried.
             */
            $table->timestamp('stock_deducted_at')->nullable()->after('return_tracking_number');
            $table->timestamp('stock_restocked_at')->nullable()->after('stock_deducted_at');
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn(['stock_deducted_at', 'stock_restocked_at']);
        });
    }
};
