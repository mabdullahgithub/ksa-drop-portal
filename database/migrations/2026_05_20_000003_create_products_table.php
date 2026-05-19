<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('handle')->unique();
            $table->string('title');
            $table->longText('body_html')->nullable();
            $table->string('vendor')->nullable();
            $table->string('product_category')->nullable();
            $table->string('type')->nullable();
            $table->json('tags')->nullable();
            $table->boolean('published')->default(true);

            // Variant / pricing
            $table->string('variant_sku')->nullable()->index();
            $table->decimal('variant_price', 12, 2)->nullable();
            $table->decimal('variant_compare_at_price', 12, 2)->nullable();
            $table->decimal('cost_per_item', 12, 2)->nullable();
            $table->integer('variant_inventory_qty')->default(0);
            $table->string('variant_inventory_policy')->default('deny');
            $table->string('variant_fulfillment_service')->nullable();
            $table->boolean('variant_requires_shipping')->default(true);
            $table->boolean('variant_taxable')->default(true);
            $table->decimal('variant_grams', 10, 2)->nullable();
            $table->string('variant_barcode')->nullable();

            // Options
            $table->string('option1_name')->nullable();
            $table->string('option1_value')->nullable();
            $table->string('option2_name')->nullable();
            $table->string('option2_value')->nullable();
            $table->string('option3_name')->nullable();
            $table->string('option3_value')->nullable();

            // SEO
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();

            // Region / pricing overrides
            $table->json('available_in')->nullable();
            $table->json('uae_prices')->nullable();
            $table->boolean('included_saudi_arabia')->default(true);
            $table->decimal('price_saudi_arabia', 12, 2)->nullable();
            $table->decimal('compare_at_price_saudi_arabia', 12, 2)->nullable();
            $table->boolean('included_pakistan')->default(false);
            $table->decimal('price_pakistan', 12, 2)->nullable();
            $table->decimal('compare_at_price_pakistan', 12, 2)->nullable();

            // Status
            $table->enum('status', ['active', 'draft', 'archived'])->default('active');

            // Primary image (first image, for convenience)
            $table->string('primary_image')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
