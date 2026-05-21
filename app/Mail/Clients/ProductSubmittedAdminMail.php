<?php

namespace App\Mail\Clients;

use App\Models\Client;
use App\Models\ClientProduct;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProductSubmittedAdminMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public ClientProduct $product,
        public Client $client
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Product Awaiting Verification — ' . $this->product->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.clients.product-submitted-admin',
            with: [
                'product'   => $this->product,
                'client'    => $this->client,
                'reviewUrl' => config('app.url') . '/client/' . $this->client->id,
            ],
        );
    }
}
