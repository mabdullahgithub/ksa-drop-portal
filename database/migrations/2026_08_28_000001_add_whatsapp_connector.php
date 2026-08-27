<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('connectors')->where('key', 'whatsapp')->exists()) {
            DB::table('connectors')->insert([
                'key' => 'whatsapp',
                'name' => 'WhatsApp',
                'description' => 'Confirm orders and verify addresses over WhatsApp via Twilio.',
                'enabled' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('connectors')->where('key', 'whatsapp')->delete();
    }
};
