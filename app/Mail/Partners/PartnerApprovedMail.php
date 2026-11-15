<?php

namespace App\Mail\Partners;

use App\Mail\BaseMailable;
use App\Models\Partner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PartnerApprovedMail extends BaseMailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Partner $partner) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'You are in: welcome to the ' . config('app.name') . ' partner program',
            replyTo: [config('mail.from.address')],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.partners.approved',
            text: 'emails.partners.approved-text',
            with: ['partner' => $this->partner, 'dashboardUrl' => config('app.url') . '/partners/dashboard', 'referralUrl' => $this->partner->referralUrl()],
        );
    }
}
