<?php

namespace App\Mail\Settings;

use App\Mail\BaseMailable;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SettingsUpdatedMail extends BaseMailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $settingType,
        public array $changes = []
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: ucfirst($this->settingType) . ' settings updated on your account',
            replyTo: [config('mail.from.address')],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.settings.settings-updated',
            text: 'emails.settings.settings-updated-text',
            with: [
                'user'        => $this->user,
                'settingType' => $this->settingType,
                'changes'     => $this->changes,
                'recipient'   => $this->user->email,
            ],
        );
    }
}
