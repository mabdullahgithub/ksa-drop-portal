<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Money now moves both ways: `in` is COD cash a rider hands in to KSA
     * Drop (every row so far), `out` is KSA Drop paying the rider what they
     * earned. The two are kept apart, never netted against each other.
     */
    public function up(): void
    {
        Schema::table('rider_payments', function (Blueprint $table) {
            $table->string('direction', 3)->default('in')->after('rider_id');
        });
    }

    public function down(): void
    {
        Schema::table('rider_payments', function (Blueprint $table) {
            $table->dropColumn('direction');
        });
    }
};
