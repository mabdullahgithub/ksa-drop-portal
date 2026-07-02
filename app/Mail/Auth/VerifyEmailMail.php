<?php

namespace App\Mail\Auth;

use App\Mail\BaseMailable;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VerifyEmailMail extends BaseMailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $verificationUrl,
        public string $expirationTime = '60 minutes'
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Please confirm your ' . config('app.name') . ' email address',
            replyTo: [config('mail.from.address')],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.auth.verify-email',
            text: 'emails.auth.verify-email-text',
            with: [
                'user'             => $this->user,
                'verificationUrl'  => $this->verificationUrl,
                'expirationTime'   => $this->expirationTime,
                'recipient'        => $this->user->email,
                'isSecurityEmail'  => true,
            ],
        );
    }
}
