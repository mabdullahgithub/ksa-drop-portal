<?php

namespace App\Notifications;

use App\Mail\Auth\WelcomeMail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class WelcomeNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        // Check if email should be sent
        $emailService = app(\App\Services\EmailService::class);
        if ($emailService->isEnabled() && $emailService->canSendEmail($notifiable, 'welcome')) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): WelcomeMail
    {
        return new WelcomeMail($notifiable);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Welcome to '.config('app.name').'!',
            'message' => 'We\'re excited to have you here. Explore the features and get started.',
            'icon' => 'sparkles',
            'action_url' => '/dashboard',
        ];
    }
}
