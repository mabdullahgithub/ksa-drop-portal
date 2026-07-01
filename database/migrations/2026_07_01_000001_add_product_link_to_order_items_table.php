<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            // Linked client inventory product (fulfilment clients)
            $table->foreignId('client_product_id')
                ->nullable()
                ->after('variant_name')
                ->constrained('client_products')
                ->nullOnDelete();

            // Linked dropshipper catalogue product
            $table->foreignId('product_id')
                ->nullable()
                ->after('client_product_id')
                ->constrained('products')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['client_product_id']);
            $table->dropForeign(['product_id']);
            $table->dropColumn(['client_product_id', 'product_id']);
        });
    }
};
