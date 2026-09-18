<?php

namespace App\Mail\Shopify;

use App\Mail\BaseMailable;
use App\Models\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ShopifyStoreConnectedMail extends BaseMailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Client $client,
        public string $shopDomain
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Shopify store is connected to ' . config('app.name'),
            replyTo: [config('mail.from.address')],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.shopify.store-connected',
            text: 'emails.shopify.store-connected-text',
            with: [
                'client'        => $this->client,
                'shopDomain'    => $this->shopDomain,
                'connectorsUrl' => config('app.url') . '/portal/connectors',
                'preheader'     => $this->shopDomain . ' is now connected to your ' . config('app.name') . ' portal.',
            ],
        );
    }
}
