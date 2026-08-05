<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('connectors')->where('key', 'imile')->exists()) {
            DB::table('connectors')->insert([
                'key' => 'imile',
                'name' => 'iMile',
                'description' => 'Track and manage iMile courier shipments and deliveries.',
                'enabled' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('connectors')->where('key', 'imile')->delete();
    }
};
