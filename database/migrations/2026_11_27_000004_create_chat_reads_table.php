<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How far each person has read each conversation: the unread counts and
     * the "Seen" mark under a message come from here.
     */
    public function up(): void
    {
        Schema::create('chat_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('chat_conversations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('last_read_message_id')->default(0);
            $table->timestamp('read_at')->nullable();

            $table->unique(['conversation_id', 'user_id']);
            $table->index(['user_id', 'conversation_id', 'last_read_message_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_reads');
    }
};
