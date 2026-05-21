<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_product_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->integer('position')->default(1);
            $table->string('alt_text')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_product_images');
    }
};
