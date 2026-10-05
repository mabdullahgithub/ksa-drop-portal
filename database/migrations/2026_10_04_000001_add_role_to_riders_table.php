<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What the person does with the app: a rider takes parcels out and
     * delivers them; an inventory manager stays at the warehouse and scans
     * parcels OUT and IN, which moves stock (App\Services\Inventory\StockScanRecorder).
     *
     * Same table and the same sign-in for both — only the app they see differs.
     */
    public function up(): void
    {
        Schema::table('riders', function (Blueprint $table) {
            $table->string('role', 30)->default('rider')->after('phone')->index();
        });
    }

    public function down(): void
    {
        Schema::table('riders', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropColumn('role');
        });
    }
};
