<?php

namespace App\Mail\Clients;

use App\Mail\BaseMailable;
use App\Models\ClientProduct;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProductVerifiedMail extends BaseMailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public ClientProduct $product) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Product approved: ' . $this->product->name,
            replyTo: [config('mail.from.address')],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.clients.product-verified',
            text: 'emails.clients.product-verified-text',
            with: [
                'product'      => $this->product,
                'inventoryUrl' => config('app.url') . '/portal/inventory',
            ],
        );
    }
}
