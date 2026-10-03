<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * WhatsApp messaging ships switched off until the integration is finished.
 *
 * The connector was added enabled, and its `enabled` flag is now the messaging
 * switch on the WhatsApp page (App\Support\WhatsAppMessaging), so without this
 * the first deploy would start messaging customers. Turned on from that page.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('connectors')->where('key', 'whatsapp')->update(['enabled' => false, 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('connectors')->where('key', 'whatsapp')->update(['enabled' => true, 'updated_at' => now()]);
    }
};
