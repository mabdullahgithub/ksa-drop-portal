<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who sent an outbound message.
 *
 * Null means the system sent it — the automated confirmation or the 24h
 * follow-up. A user id means an agent typed it by hand from the inbox. The
 * thread needs to show that difference: "the system chased them twice" and
 * "Sara answered their question" are different facts about a conversation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->foreignId('sent_by_user_id')->nullable()->after('template_key')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sent_by_user_id');
        });
    }
};
