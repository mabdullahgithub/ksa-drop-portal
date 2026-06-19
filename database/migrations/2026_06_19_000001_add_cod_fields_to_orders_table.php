<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('cod_collected_amount', 10, 2)->nullable()->after('outstanding_balance');
            $table->timestamp('cod_collected_at')->nullable()->after('cod_collected_amount');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['cod_collected_amount', 'cod_collected_at']);
        });
    }
};
