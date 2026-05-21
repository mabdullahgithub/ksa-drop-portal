<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('connectors', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->boolean('enabled')->default(false);
            $table->timestamps();
        });

        DB::table('connectors')->insert([
            ['key' => 'shopify', 'name' => 'Shopify', 'description' => 'Manage your Shopify store, products, and orders.', 'enabled' => true, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'jnt_express', 'name' => 'J&T Express', 'description' => 'Track and manage J&T courier shipments and deliveries.', 'enabled' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('connectors');
    }
};
