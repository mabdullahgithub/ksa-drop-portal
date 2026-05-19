<?php

namespace App\Mail\Auth;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TwoFactorEnabledMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Two-Factor Authentication Enabled',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.auth.two-factor-enabled',
            with: [
                'user' => $this->user,
                'recipient' => $this->user->email,
                'isSecurityEmail' => true,
            ],
        );
    }
}
