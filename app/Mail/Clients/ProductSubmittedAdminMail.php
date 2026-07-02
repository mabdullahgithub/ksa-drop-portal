<?php

namespace App\Mail\Clients;

use App\Mail\BaseMailable;
use App\Models\Client;
use App\Models\ClientProduct;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProductSubmittedAdminMail extends BaseMailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public ClientProduct $product,
        public Client $client
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Product pending review: ' . $this->product->name,
            replyTo: [config('mail.from.address')],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.clients.product-submitted-admin',
            text: 'emails.clients.product-submitted-admin-text',
            with: [
                'product'   => $this->product,
                'client'    => $this->client,
                'reviewUrl' => config('app.url') . '/client/' . $this->client->id,
            ],
        );
    }
}
