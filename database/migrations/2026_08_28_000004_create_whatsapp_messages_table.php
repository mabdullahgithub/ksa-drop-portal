<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Full conversation audit per order — every outbound template we sent and every
 * inbound reply, with Twilio's delivery lifecycle recorded against the outbound
 * ones.
 *
 * Kept as its own table rather than more columns on `orders` because delivery
 * state is per-message: the initial ping can be `read` while the follow-up is
 * still `sent`, and ops needs to see the whole thread to make sense of a reply.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('direction', 16); // outbound | inbound
            // Twilio's MessageSid. Unique so a retried status callback or a
            // redelivered inbound webhook can't create a duplicate row.
            $table->string('twilio_sid')->nullable()->unique();
            $table->string('template_key')->nullable(); // order_pending | followup | null for inbound
            $table->text('body')->nullable();
            $table->string('to_number')->nullable();
            $table->string('from_number')->nullable();

            // Twilio delivery lifecycle: queued -> sent -> delivered -> read.
            // `failed`/`undelivered` are terminal and usually mean the number
            // is not registered on WhatsApp.
            $table->string('status')->nullable()->index();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();

            $table->timestamps();

            $table->index(['order_id', 'direction']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};
