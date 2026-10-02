<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a rider owes sums every delivery they ever collected for, not a date
 * range, so the occurred_at indexes can't serve it. This one answers it from
 * the index alone, for one rider or the whole Riders page.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipment_events', function (Blueprint $table) {
            $table->index(['rider_id', 'action', 'payment_method', 'cod_amount'], 'shipment_events_rider_cash_index');
        });
    }

    public function down(): void
    {
        Schema::table('shipment_events', function (Blueprint $table) {
            $table->dropIndex('shipment_events_rider_cash_index');
        });
    }
};
