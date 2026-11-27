<?php

namespace App\Mail\Clients;

use App\Mail\BaseMailable;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * To a client: the team replied in a support chat they have not looked at.
 */
class ChatReplyMail extends BaseMailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public ChatConversation $conversation,
        public ChatMessage $reply,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "New reply: {$this->conversation->subject} [{$this->conversation->reference}]",
            replyTo: [config('mail.from.address')],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.clients.chat-reply',
            text: 'emails.clients.chat-reply-text',
            with: [
                'agent' => $this->reply->sender?->name ?? 'Our team',
                'url' => rtrim(config('app.url'), '/') . '/portal/support?chat=' . $this->conversation->id,
            ],
        );
    }
}
