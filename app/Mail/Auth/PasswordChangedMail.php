<?php

namespace App\Mail\Auth;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordChangedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public ?string $ipAddress = null
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Password Successfully Changed',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.auth.password-changed',
            with: [
                'user' => $this->user,
                'ipAddress' => $this->ipAddress,
                'recipient' => $this->user->email,
                'isSecurityEmail' => true,
            ],
        );
    }
}
