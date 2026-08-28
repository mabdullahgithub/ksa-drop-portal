<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The connector description renders in the Apps list, and still said "via
 * Twilio". Also clears any Twilio-era credential rows: on this database there
 * were none (the integration ran off .env), but an environment that had used
 * the settings UI would be left holding an orphaned encrypted auth token.
 */
return new class extends Migration
{
    private const TWILIO_KEYS = [
        'account_sid',
        'auth_token',
        'whatsapp_from',
        'template_sid_order_pending',
        'template_sid_followup',
    ];

    public function up(): void
    {
        DB::table('connectors')
            ->where('key', 'whatsapp')
            ->update([
                'description' => 'Confirm orders and verify addresses over WhatsApp via the Meta Cloud API.',
                'updated_at' => now(),
            ]);

        $connectorId = DB::table('connectors')->where('key', 'whatsapp')->value('id');

        if ($connectorId) {
            DB::table('connector_settings')
                ->where('connector_id', $connectorId)
                ->whereIn('key', self::TWILIO_KEYS)
                ->delete();
        }
    }

    public function down(): void
    {
        DB::table('connectors')
            ->where('key', 'whatsapp')
            ->update([
                'description' => 'Confirm orders and verify addresses over WhatsApp via Twilio.',
                'updated_at' => now(),
            ]);

        // Deleted credentials are not restorable — they were secrets, and
        // re-entering them in the settings UI is the correct recovery.
    }
};
