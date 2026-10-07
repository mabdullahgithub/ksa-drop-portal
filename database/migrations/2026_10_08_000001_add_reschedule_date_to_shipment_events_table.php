<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The day the customer asked for, when an attempt failed because they
     * wanted the parcel on another day. The rider picks it in the app.
     */
    public function up(): void
    {
        Schema::table('shipment_events', function (Blueprint $table) {
            $table->date('reschedule_date')->nullable()->after('reason');
        });
    }

    public function down(): void
    {
        Schema::table('shipment_events', function (Blueprint $table) {
            $table->dropColumn('reschedule_date');
        });
    }
};
