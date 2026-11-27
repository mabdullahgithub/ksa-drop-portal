<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What live chat needs to answer without reading whole threads:
 *  - how long the client has been waiting, and how long each team reply took
 *    them, so the average reply time is one query;
 *  - a voice message's waveform, made when it was recorded, so showing it
 *    never means downloading the audio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_conversations', function (Blueprint $table) {
            // The client's first unanswered message; empty once the team replies.
            $table->timestamp('waiting_since')->nullable()->after('first_response_at');
        });

        Schema::table('chat_messages', function (Blueprint $table) {
            $table->unsignedInteger('response_seconds')->nullable()->after('origin');
            $table->index(['created_at', 'response_seconds']);
        });

        Schema::table('chat_attachments', function (Blueprint $table) {
            $table->json('waveform')->nullable()->after('duration_seconds');
        });
    }

    public function down(): void
    {
        Schema::table('chat_conversations', fn (Blueprint $table) => $table->dropColumn('waiting_since'));
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropIndex(['created_at', 'response_seconds']);
            $table->dropColumn('response_seconds');
        });
        Schema::table('chat_attachments', fn (Blueprint $table) => $table->dropColumn('waveform'));
    }
};
