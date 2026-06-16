<?php

namespace App\Notifications;

use App\Mail\Settings\SettingsUpdatedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SettingsUpdatedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $settingType,
        public array $changes = []
    ) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        // Check if email should be sent
        $emailService = app(\App\Services\EmailService::class);
        if ($emailService->isEnabled() && $emailService->canSendEmail($notifiable, 'settings_updated')) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): SettingsUpdatedMail
    {
        return (new SettingsUpdatedMail($notifiable, $this->settingType, $this->changes))
            ->to($notifiable->email);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Settings Updated',
            'message' => "Your {$this->settingType} settings have been updated",
            'type' => 'settings_updated',
            'setting_type' => $this->settingType,
            'changes' => $this->changes,
            'action_url' => route('settings'),
            'icon' => 'settings',
        ];
    }
}
