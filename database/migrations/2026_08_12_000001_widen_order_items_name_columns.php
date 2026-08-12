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
        // Shopify product/variant titles aren't capped at 255 chars (merchants
        // routinely concatenate options into the title for SEO/dropshipping
        // listings). A VARCHAR(255) overflow here throws mid-webhook-request
        // (QUEUE_CONNECTION=sync), which Shopify sees as a failed delivery and
        // retries forever since the same order can never fit.
        Schema::table('order_items', function (Blueprint $table) {
            $table->text('lineitem_name')->change();
            $table->text('variant_name')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->string('lineitem_name')->change();
            $table->string('variant_name')->nullable()->change();
        });
    }
};
