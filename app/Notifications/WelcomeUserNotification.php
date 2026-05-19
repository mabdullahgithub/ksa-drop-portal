<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeUserNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public string $password,
        public string $createdBy
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $loginUrl = route('login');

        return (new MailMessage)
            ->subject('Welcome to '.config('app.name').' - Your Account Details')
            ->greeting('Hello '.$notifiable->name.',')
            ->line('Your account has been created by '.$this->createdBy.'.')
            ->line('You can now access the platform using the following credentials:')
            ->line('**Email:** '.$notifiable->email)
            ->line('**Password:** `'.$this->password.'`')
            ->line('For security reasons, we recommend changing your password after your first login.')
            ->action('Login Now', $loginUrl)
            ->line('If you have any questions, please contact your administrator.')
            ->salutation('Best regards, '.config('app.name').' Team');
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
            'message' => 'Your account has been created by '.$this->createdBy.'. Check your email for login credentials.',
            'type' => 'user_created',
            'created_by' => $this->createdBy,
            'action_url' => route('dashboard'),
            'icon' => 'user-plus',
        ];
    }
}
