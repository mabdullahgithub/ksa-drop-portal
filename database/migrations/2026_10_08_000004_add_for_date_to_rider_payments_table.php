<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The KSA day a payment settles, when staff recorded it while looking at
     * one day of the rider's money. Null for a payment against the running
     * balance.
     */
    public function up(): void
    {
        Schema::table('rider_payments', function (Blueprint $table) {
            $table->date('for_date')->nullable()->after('received_at');
            $table->index(['rider_id', 'for_date']);
        });
    }

    public function down(): void
    {
        Schema::table('rider_payments', function (Blueprint $table) {
            $table->dropIndex(['rider_id', 'for_date']);
            $table->dropColumn('for_date');
        });
    }
};
