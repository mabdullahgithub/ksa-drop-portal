<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * KSA Drop Express mints its own waybill numbers, so the database has to
     * be the one to refuse a duplicate. Scoped per courier since external
     * couriers' numbering spaces are independent; NULLs don't collide.
     */
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->unique(['courier', 'tracking_number']);
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropUnique(['courier', 'tracking_number']);
        });
    }
};
