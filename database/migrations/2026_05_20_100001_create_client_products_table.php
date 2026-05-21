<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('product_code')->unique();
            $table->string('name');
            $table->string('sku')->nullable();
            $table->text('description')->nullable();
            $table->integer('quantity')->default(0);
            $table->decimal('unit_price', 10, 2)->nullable();
            $table->string('verification_status')->default('pending');
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('client_id');
            $table->index('verification_status');
            $table->index('sku');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_products');
    }
};
