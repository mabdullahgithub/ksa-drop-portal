<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The City filter reads the distinct shipping_city values and then filters
     * with whereIn on them; both are served by this index.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->index('shipping_city');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['shipping_city']);
        });
    }
};
