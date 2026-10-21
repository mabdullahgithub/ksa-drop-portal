<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When the tracking sync last asked the courier about a shipment. The sync
 * works through the least recently asked first; it used to order by
 * updated_at, which a poll that finds nothing new never moves, so the same
 * unchanged shipments were asked every run and the rest were never reached.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->timestamp('tracking_synced_at')->nullable()->index()->after('tracking_history');
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropIndex(['tracking_synced_at']);
            $table->dropColumn('tracking_synced_at');
        });
    }
};
