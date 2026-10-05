<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What one stock scan did to stock, a row per order item in the parcel.
     *
     * The item's name and SKU are copied here rather than pointing at
     * order_items: a Shopify re-sync deletes and re-creates an order's items
     * (ShopifyOrderWriter), so their ids don't last.
     */
    public function up(): void
    {
        Schema::create('stock_scan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_scan_id')->constrained()->cascadeOnDelete();

            // The stock it moved: a catalogue product or a client's product.
            // Both null when the item matched nothing — the scan still went
            // through, and no stock changed.
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('client_product_id')->nullable()->constrained()->nullOnDelete();

            $table->string('name', 500);
            $table->string('sku')->nullable();

            // Signed: below zero left the warehouse, above zero came back.
            $table->integer('quantity');
            // The product's stock right after this scan; null when unmatched.
            $table->integer('stock_after')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_scan_items');
    }
};
