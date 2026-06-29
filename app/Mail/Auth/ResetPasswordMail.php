<?php

namespace App\Mail\Auth;

use App\Mail\BaseMailable;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ResetPasswordMail extends BaseMailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $resetUrl,
        public string $expirationTime = '60 minutes'
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your ' . config('app.name') . ' password reset link',
            replyTo: [config('mail.from.address')],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.auth.reset-password',
            text: 'emails.auth.reset-password-text',
            with: [
                'user'            => $this->user,
                'resetUrl'        => $this->resetUrl,
                'expirationTime'  => $this->expirationTime,
                'recipient'       => $this->user->email,
                'isSecurityEmail' => true,
            ],
        );
    }
}
