<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A Shopify OAuth grant must be stored the moment it's received — even before
 * a KSA Drop client is linked — so the merchant never has to run a second,
 * portal-initiated OAuth just to attach their account (Shopify App Store
 * review requirement 2.3.1 forbids manual-domain-entry / non-Shopify-surface
 * installation flows). That means client_id can no longer be required.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_shopify_connections', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
        });

        Schema::table('client_shopify_connections', function (Blueprint $table) {
            $table->foreignId('client_id')->nullable()->change();
        });

        Schema::table('client_shopify_connections', function (Blueprint $table) {
            $table->foreign('client_id')->references('id')->on('clients')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('client_shopify_connections', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
        });

        Schema::table('client_shopify_connections', function (Blueprint $table) {
            $table->foreignId('client_id')->nullable(false)->change();
        });

        Schema::table('client_shopify_connections', function (Blueprint $table) {
            $table->foreign('client_id')->references('id')->on('clients')->cascadeOnDelete();
        });
    }
};
