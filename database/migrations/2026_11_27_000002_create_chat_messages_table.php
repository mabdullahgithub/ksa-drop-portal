<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Everything in a conversation's timeline: messages, the team's internal
     * notes, and system events (assigned, resolved, reopened...). `internal`
     * rows never leave the team: not to the portal, not to the client's
     * channel, not in an email.
     */
    public function up(): void
    {
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('chat_conversations')->cascadeOnDelete();

            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('sender_type', 10); // client | staff | system
            // Who sent it, as they were at the time: the name and role stay
            // on the message if the account is renamed, changes role or is
            // deleted later.
            $table->string('sender_name')->nullable();
            $table->string('sender_role', 60)->nullable();
            $table->string('kind', 10)->default('message'); // message | note | event
            $table->boolean('internal')->default(false);

            $table->text('body')->nullable();
            $table->json('meta')->nullable();

            // The browser's id for a message it sent, so a retried send is
            // stored once.
            $table->uuid('client_uuid')->nullable();

            $table->timestamp('edited_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['conversation_id', 'id']);
            $table->unique(['conversation_id', 'client_uuid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
