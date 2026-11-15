<?php

namespace App\Mail\Partners;

use App\Mail\BaseMailable;
use App\Models\Partner;
use App\Models\PartnerPayoutRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PartnerPayoutPaidMail extends BaseMailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Partner $partner, public PartnerPayoutRequest $payout) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your payout has been sent',
            replyTo: [config('mail.from.address')],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.partners.payout-paid',
            text: 'emails.partners.payout-paid-text',
            with: ['partner' => $this->partner, 'payout' => $this->payout, 'dashboardUrl' => config('app.url') . '/partners/payouts'],
        );
    }
}
