<?php

namespace App\Mail\Users;

use App\Mail\BaseMailable;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UserUpdatedMail extends BaseMailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $admin,
        public User $updatedUser,
        public User $updatedBy,
        public array $changes = [],
        public ?string $userUrl = null
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Team member account updated: ' . $this->updatedUser->name,
            replyTo: [config('mail.from.address')],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.users.user-updated',
            text: 'emails.users.user-updated-text',
            with: [
                'admin'       => $this->admin,
                'updatedUser' => $this->updatedUser,
                'updatedBy'   => $this->updatedBy,
                'changes'     => $this->changes,
                'userUrl'     => $this->userUrl,
                'recipient'   => $this->admin->email,
            ],
        );
    }
}
