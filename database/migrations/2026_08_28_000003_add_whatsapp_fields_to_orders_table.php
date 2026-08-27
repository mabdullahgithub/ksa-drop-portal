<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WhatsApp confirmation state, denormalised onto the order.
 *
 * `whatsapp_status` tracks the *conversation flow* (did we ping, follow up, get
 * a reply, give up) and is what the 24h/48h sweep drives off. Per-message
 * *delivery* state (queued/sent/delivered/read/failed) lives on
 * `whatsapp_messages` instead, because one order accumulates several messages
 * and each has its own delivery lifecycle.
 *
 * The two `*_delivered_at` / `*_read_at` mirrors below are the latest values
 * across those messages, kept here purely so the orders list can display and
 * filter on them without a join.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // sent | followup_sent | replied | confirmed | graveyard | failed
            $table->string('whatsapp_status')->nullable()->after('call_notes')->index();
            $table->string('whatsapp_phone_e164')->nullable()->after('whatsapp_status')->index();
            $table->timestamp('whatsapp_sent_at')->nullable()->after('whatsapp_phone_e164');
            $table->timestamp('whatsapp_followup_sent_at')->nullable()->after('whatsapp_sent_at');
            $table->timestamp('whatsapp_replied_at')->nullable()->after('whatsapp_followup_sent_at');
            $table->timestamp('whatsapp_delivered_at')->nullable()->after('whatsapp_replied_at');
            $table->timestamp('whatsapp_read_at')->nullable()->after('whatsapp_delivered_at');
            $table->text('whatsapp_reply_message')->nullable()->after('whatsapp_read_at');

            // Drives the sweep query: "orders in stage X whose stage timestamp
            // has aged past the 24h threshold".
            $table->index(['whatsapp_status', 'whatsapp_sent_at']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['whatsapp_status', 'whatsapp_sent_at']);
            $table->dropColumn([
                'whatsapp_status',
                'whatsapp_phone_e164',
                'whatsapp_sent_at',
                'whatsapp_followup_sent_at',
                'whatsapp_replied_at',
                'whatsapp_delivered_at',
                'whatsapp_read_at',
                'whatsapp_reply_message',
            ]);
        });
    }
};
