<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // null = normal order (manual / CSV / auto-synced Shopify) — visible everywhere
            // pending_review = manual-approval Shopify order awaiting client action (hidden)
            // approved = client approved a pending order — behaves as normal
            // dismissed = client rejected — hidden everywhere
            $table->enum('shopify_sync_status', ['pending_review', 'approved', 'dismissed'])
                ->nullable()
                ->default(null)
                ->after('source')
                ->index();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('shopify_sync_status');
        });
    }
};
