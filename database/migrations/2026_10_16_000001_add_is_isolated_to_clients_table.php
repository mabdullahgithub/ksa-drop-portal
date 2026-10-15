<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An isolated client is a store that lives apart from the platform: its
 * orders, shipments, products and payments never reach an admin list, a stat,
 * a report or a courier sync. Only its own sign-in, and staff assigned to it by
 * name, can see it. See App\Support\ClientAccess.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->boolean('is_isolated')->default(false)->index()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropIndex(['is_isolated']);
            $table->dropColumn('is_isolated');
        });
    }
};
