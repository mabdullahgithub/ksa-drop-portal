<?php

namespace App\Mail\Clients;

use App\Mail\BaseMailable;
use App\Models\InventoryRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The email behind both inventory request notifications: to the team when a
 * client asks, to the client when it is decided. The notification supplies
 * the wording; this lays it out.
 */
class InventoryRequestMail extends BaseMailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public InventoryRequest $request,
        public string $subjectLine,
        public string $headline,
        public string $intro,
        public string $alert,
        public string $buttonLabel,
        public string $buttonUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine,
            replyTo: [config('mail.from.address')],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.clients.inventory-request',
            text: 'emails.clients.inventory-request-text',
            with: [
                'product' => $this->request->product()->withTrashed()->first(),
                'client' => $this->request->client,
            ],
        );
    }
}
