<?php

namespace App\Mail\Users;

use App\Mail\BaseMailable;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UserCreatedMail extends BaseMailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $admin,
        public User $createdUser,
        public User $createdBy,
        public ?string $role = null,
        public ?string $userUrl = null
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New team member added: ' . $this->createdUser->name,
            replyTo: [config('mail.from.address')],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.users.user-created',
            text: 'emails.users.user-created-text',
            with: [
                'admin'       => $this->admin,
                'createdUser' => $this->createdUser,
                'createdBy'   => $this->createdBy,
                'role'        => $this->role,
                'userUrl'     => $this->userUrl,
                'recipient'   => $this->admin->email,
            ],
        );
    }
}
