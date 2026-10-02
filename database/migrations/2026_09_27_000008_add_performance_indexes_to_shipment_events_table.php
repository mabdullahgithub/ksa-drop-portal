<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every rider query reads events by the rider's own clock (occurred_at) —
 * the Riders page "Top performers", the rider app's history and today's
 * stats — which the created_at index can't serve.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipment_events', function (Blueprint $table) {
            // Leaderboard: one range scan over the dates, answered from the
            // index alone (no row reads) — every column it groups or sums.
            $table->index(['occurred_at', 'rider_id', 'action', 'shipment_id', 'cod_amount'], 'shipment_events_occurred_covering_index');

            // One rider's dates: their breakdown, parcel list, app history
            // and today's stats. Carries action and shipment_id so "which
            // parcels" and "last update per parcel" never touch the rows.
            // Also backs the rider_id foreign key.
            $table->index(['rider_id', 'occurred_at', 'action', 'shipment_id'], 'shipment_events_rider_occurred_index');
        });

        // Nothing reads a rider's events by created_at; left in place it only
        // costs writes and tempts the planner away from the index above.
        Schema::table('shipment_events', function (Blueprint $table) {
            $table->dropIndex(['rider_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('shipment_events', function (Blueprint $table) {
            $table->index(['rider_id', 'created_at']);
        });

        Schema::table('shipment_events', function (Blueprint $table) {
            $table->dropIndex('shipment_events_occurred_covering_index');
            $table->dropIndex('shipment_events_rider_occurred_index');
        });
    }
};
