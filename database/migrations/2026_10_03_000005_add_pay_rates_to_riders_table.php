<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What KSA Drop pays this rider: one rate per delivered order, one per
     * attempt. Agreed rider by rider, so each has their own; null until the
     * admin sets them, and the rider earns nothing until then.
     */
    public function up(): void
    {
        Schema::table('riders', function (Blueprint $table) {
            $table->decimal('delivery_rate', 8, 2)->nullable()->after('iban');
            $table->decimal('attempt_rate', 8, 2)->nullable()->after('delivery_rate');
        });
    }

    public function down(): void
    {
        Schema::table('riders', function (Blueprint $table) {
            $table->dropColumn(['delivery_rate', 'attempt_rate']);
        });
    }
};
