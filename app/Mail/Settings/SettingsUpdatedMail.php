<?php

namespace App\Mail\Settings;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SettingsUpdatedMail extends Mailable implements ShouldQueue
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
            to: [$this->user->email],
            subject: ucfirst($this->settingType) . ' Settings Updated',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.settings.settings-updated',
            with: [
                'user' => $this->user,
                'settingType' => $this->settingType,
                'changes' => $this->changes,
                'recipient' => $this->user->email,
            ],
        );
    }
}
