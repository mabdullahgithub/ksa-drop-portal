<?php

namespace App\Mail\Partners;

use App\Mail\BaseMailable;
use App\Models\Partner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PartnerRejectedMail extends BaseMailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Partner $partner) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'An update on your partner application',
            replyTo: [config('mail.from.address')],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.partners.rejected',
            text: 'emails.partners.rejected-text',
            with: ['partner' => $this->partner],
        );
    }
}
