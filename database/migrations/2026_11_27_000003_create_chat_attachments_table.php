<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Files sent with a chat message. Kept on the private disk and only
     * streamed through a route that checks the person may read the
     * conversation.
     */
    public function up(): void
    {
        Schema::create('chat_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('chat_messages')->cascadeOnDelete();
            $table->foreignId('conversation_id')->constrained('chat_conversations')->cascadeOnDelete();

            $table->string('disk', 20);
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 120);
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_attachments');
    }
};
