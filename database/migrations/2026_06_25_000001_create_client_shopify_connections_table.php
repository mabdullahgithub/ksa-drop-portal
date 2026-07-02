<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_shopify_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('shop_domain');                    // e.g. "mystore.myshopify.com"
            $table->text('access_token');                     // encrypted (1h expiring offline token)
            $table->text('refresh_token')->nullable();        // encrypted (90d, rotated on refresh)
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamp('refresh_token_expires_at')->nullable();
            $table->string('scope')->nullable();              // e.g. "read_orders"
            $table->enum('sync_mode', ['auto_sync', 'manual_approval'])->default('auto_sync');
            $table->enum('status', ['active', 'disconnected', 'error'])->default('active');
            $table->boolean('webhooks_registered')->default(false);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->timestamps();

            $table->unique('shop_domain');                    // one connection per Shopify store
            $table->unique('client_id');                      // one Shopify store per client
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_shopify_connections');
    }
};
