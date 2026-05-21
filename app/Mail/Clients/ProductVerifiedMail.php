<?php

namespace App\Mail\Clients;

use App\Models\ClientProduct;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProductVerifiedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public ClientProduct $product) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Product Has Been Verified — ' . $this->product->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.clients.product-verified',
            with: [
                'product'      => $this->product,
                'inventoryUrl' => config('app.url') . '/portal/inventory',
            ],
        );
    }
}
