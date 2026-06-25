<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('connectors')->insert([
            ['key' => 'coming_soon', 'name' => 'Something Special', 'description' => 'Exciting new integrations coming for our valued clients. Stay tuned!', 'enabled' => false, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        DB::table('connectors')->where('key', 'coming_soon')->delete();
    }
};
