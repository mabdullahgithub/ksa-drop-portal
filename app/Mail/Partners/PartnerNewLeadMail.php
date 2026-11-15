<?php

namespace App\Mail\Partners;

use App\Mail\BaseMailable;
use App\Models\Partner;
use App\Models\PartnerLead;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PartnerNewLeadMail extends BaseMailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Partner $partner, public PartnerLead $lead) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'A new seller came through your referral link',
            replyTo: [config('mail.from.address')],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.partners.new-lead',
            text: 'emails.partners.new-lead-text',
            with: ['partner' => $this->partner, 'lead' => $this->lead, 'dashboardUrl' => config('app.url') . '/partners/referrals'],
        );
    }
}
