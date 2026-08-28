<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Twilio → Meta WhatsApp Cloud API.
 *
 * `twilio_sid` held Twilio's MessageSid (`SM…`); it now holds Meta's message ID
 * (`wamid.…`). Renamed rather than replaced so the existing conversation history
 * survives the migration — the old SM… values stay readable in the inbox, they
 * simply stop receiving status updates because Twilio's webhooks are gone.
 *
 * The unique index rides along with the rename and is what makes webhook
 * redelivery idempotent, so it must survive.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->renameColumn('twilio_sid', 'provider_message_id');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->renameColumn('provider_message_id', 'twilio_sid');
        });
    }
};
