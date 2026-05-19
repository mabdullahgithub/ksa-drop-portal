<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');

            $table->string('lineitem_name');
            $table->integer('lineitem_quantity')->default(1);
            $table->decimal('lineitem_price', 10, 2)->default(0);
            $table->decimal('lineitem_compare_at_price', 10, 2)->nullable();
            $table->string('lineitem_sku')->nullable();
            $table->boolean('lineitem_requires_shipping')->default(true);
            $table->boolean('lineitem_taxable')->default(true);
            $table->string('lineitem_fulfillment_status')->nullable();
            $table->decimal('lineitem_discount', 10, 2)->default(0);
            $table->string('variant_name')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
