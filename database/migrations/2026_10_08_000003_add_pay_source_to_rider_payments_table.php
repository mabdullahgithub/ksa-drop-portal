<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which of the rider's earnings a payout settles: deliveries the
     * customer paid in cash, by card or by transfer, prepaid ones, or failed
     * attempts (App\Services\Riders\RiderPay::SOURCES). Payouts recorded
     * before this have none, and come off the earnings in that order.
     */
    public function up(): void
    {
        Schema::table('rider_payments', function (Blueprint $table) {
            $table->string('pay_source', 10)->nullable()->after('cod_method');
        });
    }

    public function down(): void
    {
        Schema::table('rider_payments', function (Blueprint $table) {
            $table->dropColumn('pay_source');
        });
    }
};
