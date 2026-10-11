<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The dropshipper whose sell count this scan item changed (dropship_sales),
     * null when it counted for nobody. An IN reads it off the OUT before it,
     * so it takes back exactly the sale that was counted.
     */
    public function up(): void
    {
        Schema::table('stock_scan_items', function (Blueprint $table) {
            $table->foreignId('sale_client_id')->nullable()->after('client_product_id')
                ->constrained('clients')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_scan_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sale_client_id');
        });
    }
};
