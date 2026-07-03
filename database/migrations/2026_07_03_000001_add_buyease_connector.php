<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('connectors')->insert([
            ['key' => 'buyease', 'name' => 'BuyEase COD Form & Upsells', 'description' => 'AI-powered commerce tools helping Shopify merchants sell smarter, not harder — and prevent fraud.', 'enabled' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // BuyEase replaces the "coming soon" teaser card on the client portal.
        DB::table('connectors')->where('key', 'coming_soon')->update(['enabled' => false, 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('connectors')->where('key', 'buyease')->delete();
    }
};
