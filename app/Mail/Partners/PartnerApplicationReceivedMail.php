<?php

namespace App\Mail\Partners;

use App\Mail\BaseMailable;
use App\Models\Partner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PartnerApplicationReceivedMail extends BaseMailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Partner $partner) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your partner application was received',
            replyTo: [config('mail.from.address')],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.partners.application-received',
            text: 'emails.partners.application-received-text',
            with: ['partner' => $this->partner, 'dashboardUrl' => config('app.url') . '/partners/dashboard'],
        );
    }
}
