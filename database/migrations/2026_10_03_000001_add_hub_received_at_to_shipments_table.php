<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When the hub got a returned or cancelled KSA Express parcel back from
     * its rider. Until then the parcel is still in the rider's hands and
     * stays in their app as "Return to KSA Drop".
     */
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->timestamp('hub_received_at')->nullable()->after('cancel_reason');
        });

        // Parcels that ended before hand-backs were recorded: nobody can say
        // now whether they came back, so count them as back rather than have
        // every old one appear in a rider's app as still to hand in.
        DB::table('shipments')
            ->whereNotNull('rider_id')
            ->whereIn('status', ['cancelled', 'returned'])
            ->update(['hub_received_at' => DB::raw('COALESCE(cancelled_at, updated_at)')]);
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn('hub_received_at');
        });
    }
};
