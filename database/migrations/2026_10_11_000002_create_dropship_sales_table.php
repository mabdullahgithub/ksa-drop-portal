<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How much of each catalogue product a dropshipper has sold: one row per
     * client and product, counted as their parcels are scanned OUT of the
     * warehouse — see App\Services\Inventory\StockScanRecorder.
     */
    public function up(): void
    {
        Schema::create('dropship_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            // Parcels that carried the product, and the units in them.
            $table->integer('orders')->default(0);
            $table->integer('units')->default(0);

            $table->timestamps();

            $table->unique(['client_id', 'product_id']);
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dropship_sales');
    }
};
