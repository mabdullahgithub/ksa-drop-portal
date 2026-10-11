<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Images a client added in a details request. They sit in
     * client-product-requests/{request} on the public disk until the request
     * is approved, then move to the product's own folder.
     */
    public function up(): void
    {
        Schema::create('inventory_request_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_request_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->unsignedInteger('position')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_request_images');
    }
};
