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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->string('shopify_order_id')->nullable()->index();

            // Customer Information
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_phone')->nullable()->index();

            // Billing Address
            $table->string('billing_name')->nullable();
            $table->string('billing_street')->nullable();
            $table->string('billing_address1')->nullable();
            $table->string('billing_address2')->nullable();
            $table->string('billing_company')->nullable();
            $table->string('billing_city')->nullable();
            $table->string('billing_zip')->nullable();
            $table->string('billing_province')->nullable();
            $table->string('billing_country', 2)->nullable();
            $table->string('billing_phone')->nullable();

            // Shipping Address
            $table->string('shipping_name')->nullable();
            $table->string('shipping_street')->nullable();
            $table->string('shipping_address1')->nullable();
            $table->string('shipping_address2')->nullable();
            $table->string('shipping_company')->nullable();
            $table->string('shipping_city')->nullable();
            $table->string('shipping_zip')->nullable();
            $table->string('shipping_province')->nullable();
            $table->string('shipping_country', 2)->nullable();
            $table->string('shipping_phone')->nullable();

            // Order Status
            $table->string('financial_status')->default('pending')->index();
            $table->string('fulfillment_status')->default('unfulfilled')->index();

            // Payment Information
            $table->string('payment_method')->nullable();
            $table->string('payment_reference')->nullable();
            $table->string('currency', 3)->default('SAR');

            // Pricing
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('shipping_cost', 10, 2)->default(0);
            $table->decimal('taxes', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->string('discount_code')->nullable();
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->string('shipping_method')->nullable();
            $table->decimal('outstanding_balance', 10, 2)->default(0);
            $table->decimal('refunded_amount', 10, 2)->default(0);

            // Timestamps
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            // Additional Information
            $table->text('notes')->nullable();
            $table->json('note_attributes')->nullable();
            $table->json('tags')->nullable();
            $table->string('risk_level')->nullable();
            $table->string('source')->nullable();
            $table->string('vendor')->nullable();

            // Marketing & Tracking
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('utm_id')->nullable();
            $table->string('ip_address')->nullable();
            $table->boolean('accepts_marketing')->default(false);

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('created_at');
            $table->index('total');
            $table->index(['fulfillment_status', 'created_at']);
            $table->index(['financial_status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
