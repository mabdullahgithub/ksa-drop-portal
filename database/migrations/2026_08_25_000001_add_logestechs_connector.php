<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('connectors')->where('key', 'logestechs')->exists()) {
            DB::table('connectors')->insert([
                'key' => 'logestechs',
                'name' => 'LogesTechs',
                'description' => 'Create, track and manage LogesTechs (Navix) courier shipments.',
                'enabled' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('connectors')->where('key', 'logestechs')->delete();
    }
};
