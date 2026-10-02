<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What KSA Drop owes a rider, one row per visit that earned something:
     * a delivery, or an attempt the customer didn't take the parcel on. An
     * order tried twice and then delivered is three rows. Written with the
     * rider's update that earned it — see App\Services\Riders\RiderPay.
     *
     * The amount is the rate in force at that moment, so changing a rate
     * later never rewrites what was already earned.
     */
    public function up(): void
    {
        Schema::create('rider_earnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rider_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            // The update that earned it: one earning per update, ever.
            $table->foreignId('shipment_event_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('type', 20); // delivered | attempted
            $table->decimal('amount', 8, 2);
            $table->timestamp('earned_at');
            $table->timestamps();

            $table->index(['rider_id', 'earned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rider_earnings');
    }
};
