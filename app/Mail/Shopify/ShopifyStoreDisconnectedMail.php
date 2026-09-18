<?php

namespace App\Mail\Shopify;

use App\Mail\BaseMailable;
use App\Models\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ShopifyStoreDisconnectedMail extends BaseMailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Client $client,
        public string $shopDomain,
        public string $reason
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Shopify store has been disconnected from ' . config('app.name'),
            replyTo: [config('mail.from.address')],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.shopify.store-disconnected',
            text: 'emails.shopify.store-disconnected-text',
            with: [
                'client'        => $this->client,
                'shopDomain'    => $this->shopDomain,
                'reason'        => $this->reason,
                'connectorsUrl' => config('app.url') . '/portal/connectors',
                'preheader'     => $this->shopDomain . ' is no longer connected to your ' . config('app.name') . ' portal.',
            ],
        );
    }
}
