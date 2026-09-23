<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('connectors')->where('key', 'ksadrop_express')->exists()) {
            DB::table('connectors')->insert([
                'key' => 'ksadrop_express',
                'name' => 'KSA Express',
                'description' => 'Ship orders with our own in-house courier, KSA Express.',
                'enabled' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('connectors')->where('key', 'ksadrop_express')->delete();
    }
};
