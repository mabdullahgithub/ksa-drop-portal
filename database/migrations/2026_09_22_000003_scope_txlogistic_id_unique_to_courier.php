<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * txlogistic_id is the order number we hand the courier, and it was unique
     * across the whole table. That made an order unbookable a second time:
     * cancel a shipment, hand the same order to another courier, and the
     * courier accepts and prints a waybill while our insert dies on the old
     * row's key — the booking exists at the courier and nowhere here.
     *
     * Uniqueness only ever needs to hold per courier (each one keys its own
     * orders off it, and the webhooks look it up that way), so scope it.
     */
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropUnique('shipments_txlogistic_id_unique');
            $table->unique(['courier', 'txlogistic_id']);
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropUnique(['courier', 'txlogistic_id']);
            $table->unique('txlogistic_id');
        });
    }
};
