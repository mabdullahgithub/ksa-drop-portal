<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which of the rider's COD a payment from them settles: what customers
     * paid in cash, by card or by transfer. Each is owed and handed in on
     * its own. Every payment so far was for cash, the only COD owed until
     * now; payouts to the rider (`out`) have none.
     */
    public function up(): void
    {
        Schema::table('rider_payments', function (Blueprint $table) {
            $table->string('cod_method', 10)->nullable()->after('method');
        });

        DB::table('rider_payments')->where('direction', 'in')->update(['cod_method' => 'cash']);
    }

    public function down(): void
    {
        Schema::table('rider_payments', function (Blueprint $table) {
            $table->dropColumn('cod_method');
        });
    }
};
