<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A support conversation between one client and the team, ticket-style:
     * each has its own subject and status, and a client can have several
     * open at once. See App\Models\ChatConversation.
     */
    public function up(): void
    {
        Schema::create('chat_conversations', function (Blueprint $table) {
            $table->id();
            // CH-000123, set right after insert from the id.
            $table->string('reference', 20)->nullable()->unique();

            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();

            $table->string('subject', 160);
            $table->string('category', 20)->default('general');
            $table->string('priority', 10)->default('normal');
            $table->string('status', 10)->default('open'); // open | pending | on_hold | resolved | closed

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('opened_by', 10); // client | staff

            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('last_client_message_at')->nullable();
            $table->timestamp('last_staff_message_at')->nullable();
            $table->timestamp('first_response_at')->nullable();

            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->unsignedInteger('reopened_count')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'last_message_at']);
            $table->index(['client_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_conversations');
    }
};
